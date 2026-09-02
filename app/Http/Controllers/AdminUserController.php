<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Club;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AdminUserController extends Controller
{
    private const MANAGEABLE_ROLES = ['SUPER_ADMIN', 'CLUB_ADMIN'];

    public function index(Request $request)
    {
        $data = $request->validate(['per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);

        return User::query()
            ->with(['roles', 'clubs'])
            ->latest('id')
            ->paginate($data['per_page'] ?? 20);
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());

        $user = DB::transaction(function () use ($request, $data) {
            $roleIds = $this->manageableRoleIds($data['role_ids']);
            $clubIds = $this->existingClubIds($data['club_ids'] ?? []);
            $this->ensureClubAdminHasClub($roleIds, $clubIds);

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'is_active' => $data['is_active'] ?? true,
            ]);
            $user->roles()->sync($roleIds);
            $this->syncClubs($user, $clubIds, $request->user()->id);
            $this->audit($request, 'CREATE', $user, null, $this->snapshot($user));

            return $user;
        });

        return response()->json($user->load(['roles', 'clubs']), 201);
    }

    public function show(int $id)
    {
        return User::query()->with(['roles', 'clubs'])->findOrFail($id);
    }

    public function update(Request $request, int $id)
    {
        $data = $request->validate($this->rules(true, $id));

        $user = DB::transaction(function () use ($request, $data, $id) {
            $user = User::query()->lockForUpdate()->findOrFail($id);
            $before = $this->snapshot($user);
            $roleIds = array_key_exists('role_ids', $data)
                ? $this->manageableRoleIds($data['role_ids'])
                : $user->roles()->pluck('roles.id')->all();
            $clubIds = array_key_exists('club_ids', $data)
                ? $this->existingClubIds($data['club_ids'])
                : $user->clubs()->pluck('clubs.id')->all();

            $this->ensureClubAdminHasClub($roleIds, $clubIds);
            if ($this->removesActiveSuperAdmin($user, $roleIds, $data)) {
                $this->ensureAnotherActiveSuperAdmin($user->id);
            }

            $attributes = collect($data)->only(['name', 'email', 'is_active'])->all();
            if (array_key_exists('password', $data) && $data['password'] !== null) {
                $attributes['password'] = Hash::make($data['password']);
            }
            $user->update($attributes);
            if (array_key_exists('role_ids', $data)) {
                $user->roles()->sync($roleIds);
            }
            if (array_key_exists('club_ids', $data)) {
                $this->syncClubs($user, $clubIds, $request->user()->id);
            }
            $this->audit($request, 'UPDATE', $user, $before, $this->snapshot($user));

            return $user;
        });

        return $user->load(['roles', 'clubs']);
    }

    public function destroy(Request $request, int $id)
    {
        DB::transaction(function () use ($request, $id) {
            $user = User::query()->lockForUpdate()->findOrFail($id);
            $before = $this->snapshot($user);

            if ($user->is_active && $user->hasRole('SUPER_ADMIN')) {
                $this->ensureAnotherActiveSuperAdmin($user->id);
            }

            $user->roles()->detach();
            $user->clubs()->detach();
            $user->delete();
            $this->audit($request, 'DELETE', $user, $before, null);
        });

        return response()->noContent();
    }

    private function rules(bool $updating = false, ?int $userId = null): array
    {
        $required = $updating ? 'sometimes' : 'required';

        return [
            'name' => [$required, 'string', 'max:120'],
            'email' => [$required, 'email', 'max:180', Rule::unique('users', 'email')->ignore($userId)],
            'password' => $updating
                ? ['sometimes', 'nullable', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()]
                : ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
            'is_active' => ['sometimes', 'boolean'],
            'role_ids' => [$required, 'array', 'min:1'],
            'role_ids.*' => ['integer', 'distinct', 'exists:roles,id'],
            'club_ids' => ['sometimes', 'array'],
            'club_ids.*' => ['integer', 'distinct', 'exists:clubs,id'],
        ];
    }

    private function manageableRoleIds(array $roleIds): array
    {
        $roles = Role::query()->whereIn('id', $roleIds)->whereIn('name', self::MANAGEABLE_ROLES)->get(['id', 'name']);
        if ($roles->count() !== count($roleIds)) {
            throw ValidationException::withMessages(['role_ids' => ['Only SUPER_ADMIN and CLUB_ADMIN roles can be assigned.']]);
        }

        return $roles->pluck('id')->all();
    }

    private function existingClubIds(array $clubIds): array
    {
        $clubs = Club::query()->whereIn('id', $clubIds)->pluck('id')->all();
        if (count($clubs) !== count($clubIds)) {
            throw ValidationException::withMessages(['club_ids' => ['One or more clubs do not exist.']]);
        }

        return $clubs;
    }

    private function ensureClubAdminHasClub(array $roleIds, array $clubIds): void
    {
        $isClubAdmin = Role::query()->whereIn('id', $roleIds)->where('name', 'CLUB_ADMIN')->exists();
        if ($isClubAdmin && $clubIds === []) {
            throw ValidationException::withMessages(['club_ids' => ['CLUB_ADMIN users must be assigned to at least one club.']]);
        }
    }

    private function removesActiveSuperAdmin(User $user, array $roleIds, array $data): bool
    {
        if (! $user->is_active || ! $user->hasRole('SUPER_ADMIN')) {
            return false;
        }

        $remainsActive = ! array_key_exists('is_active', $data) || $data['is_active'];
        $remainsSuperAdmin = Role::query()->whereIn('id', $roleIds)->where('name', 'SUPER_ADMIN')->exists();

        return ! $remainsActive || ! $remainsSuperAdmin;
    }

    private function ensureAnotherActiveSuperAdmin(int $userId): void
    {
        $hasAnother = User::query()
            ->whereKeyNot($userId)
            ->where('is_active', true)
            ->whereHas('roles', fn ($query) => $query->where('name', 'SUPER_ADMIN'))
            ->lockForUpdate()
            ->exists();
        if (! $hasAnother) {
            throw ValidationException::withMessages(['user' => ['At least one active SUPER_ADMIN must remain.']]);
        }
    }

    private function syncClubs(User $user, array $clubIds, int $assignedBy): void
    {
        $user->clubs()->sync(collect($clubIds)->mapWithKeys(fn (int $clubId) => [
            $clubId => ['assigned_at' => now(), 'assigned_by' => $assignedBy],
        ])->all());
    }

    private function snapshot(User $user): array
    {
        return [
            ...collect($user->getAttributes())->except('password')->all(),
            'role_ids' => $user->roles()->pluck('roles.id')->all(),
            'club_ids' => $user->clubs()->pluck('clubs.id')->all(),
        ];
    }

    private function audit(Request $request, string $action, User $user, ?array $before, ?array $after): void
    {
        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => $action,
            'entity_type' => 'users',
            'entity_id' => $user->id,
            'before_data' => $before,
            'after_data' => $after,
            'ip_address' => $request->ip(),
        ]);
    }
}
