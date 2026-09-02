<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Club;
use App\Models\Fixture;
use App\Models\MatchGame;
use App\Models\News;
use App\Models\Player;
use App\Models\Round;
use App\Models\Tournament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminResourceController extends Controller
{
    private const MODELS = ['clubs' => Club::class, 'categories' => Category::class, 'tournaments' => Tournament::class, 'rounds' => Round::class, 'fixtures' => Fixture::class, 'matches' => MatchGame::class, 'players' => Player::class, 'news' => News::class];

    public function index(Request $request, string $resource)
    {
        return $this->model($resource)::query()->latest('id')->paginate($request->integer('per_page', 20));
    }

    public function store(Request $request, string $resource)
    {
        $data = $request->validate($this->rules($resource));
        if ($resource === 'news') $data['author_id'] = $request->user()->id;
        if ($resource === 'news' && $data['status'] === 'PUBLISHED' && empty($data['published_at'])) $data['published_at'] = now();
        $model = $this->model($resource)::create($data);
        $this->audit($request, 'CREATE', $model, null, $model->getAttributes());
        return response()->json($model, 201);
    }

    public function show(string $resource, int $id) { return $this->model($resource)::findOrFail($id); }

    public function update(Request $request, string $resource, int $id)
    {
        $model = $this->model($resource)::findOrFail($id); $before = $model->getAttributes();
        $model->update($request->validate($this->rules($resource, $model)));
        $this->audit($request, 'UPDATE', $model, $before, $model->fresh()->getAttributes());
        return $model->fresh();
    }

    public function destroy(Request $request, string $resource, int $id)
    {
        $model = $this->model($resource)::findOrFail($id); $before = $model->getAttributes(); $model->delete();
        $this->audit($request, 'DELETE', $model, $before, null);
        return response()->noContent();
    }

    private function model(string $resource): string { abort_unless(isset(self::MODELS[$resource]), 404); return self::MODELS[$resource]; }
    private function rules(string $resource, ?Model $model = null): array
    {
        $unique = fn (string $column, string $table) => [Rule::unique($table, $column)->ignore($model?->id)];
        return match ($resource) {
            'clubs' => ['name' => ['required', 'string', 'max:120'], 'slug' => ['required', 'string', 'max:140', ...$unique('slug', 'clubs')], 'short_name' => ['nullable', 'string', 'max:60'], 'logo_url' => ['nullable', 'url', 'max:500'], 'description' => ['nullable', 'string'], 'is_active' => ['boolean']],
            'categories' => ['name' => ['required', 'string', 'max:80', ...$unique('name', 'categories')], 'slug' => ['required', 'string', 'max:90', ...$unique('slug', 'categories')], 'sort_order' => ['integer', 'min:0'], 'is_active' => ['boolean']],
            'tournaments' => ['name' => ['required', 'string', 'max:120'], 'season' => ['required', 'string', 'max:20'], 'status' => ['required', Rule::in(['DRAFT', 'ACTIVE', 'FINISHED', 'ARCHIVED'])], 'points_win' => ['integer', 'min:0', 'max:255'], 'points_draw' => ['integer', 'min:0', 'max:255'], 'points_loss' => ['integer', 'min:0', 'max:255'], 'tiebreak_rules' => ['nullable', 'array']],
            'rounds' => ['tournament_id' => ['required', 'exists:tournaments,id'], 'number' => ['required', 'integer', 'min:1'], 'scheduled_date' => ['required', 'date'], 'status' => ['required', Rule::in(['SCHEDULED', 'SUSPENDED', 'FINISHED', 'CANCELLED'])], 'notes' => ['nullable', 'string']],
            'fixtures' => ['round_id' => ['required', 'exists:rounds,id'], 'home_club_id' => ['required', 'different:away_club_id', 'exists:clubs,id'], 'away_club_id' => ['required', 'exists:clubs,id'], 'venue' => ['nullable', 'string', 'max:160'], 'scheduled_at' => ['nullable', 'date'], 'status' => ['required', Rule::in(['SCHEDULED', 'SUSPENDED', 'POSTPONED', 'CANCELLED', 'FINISHED'])], 'notes' => ['nullable', 'string']],
            'matches' => ['fixture_id' => ['required', 'exists:fixtures,id'], 'category_id' => ['required', 'exists:categories,id'], 'status' => ['required', Rule::in(['SCHEDULED', 'IN_PROGRESS', 'FINISHED', 'SUSPENDED', 'POSTPONED', 'CANCELLED'])], 'result_status' => ['sometimes', Rule::in(['NONE', 'PENDING_CONFIRMATION', 'CONFIRMED', 'IN_CONFLICT', 'VALIDATED'])], 'started_at' => ['nullable', 'date'], 'ended_at' => ['nullable', 'date', 'after_or_equal:started_at']],
            'players' => ['first_name' => ['required', 'string', 'max:80'], 'last_name' => ['required', 'string', 'max:80'], 'photo_url' => ['nullable', 'url', 'max:500'], 'position' => ['nullable', 'string', 'max:20'], 'shirt_number' => ['nullable', 'integer', 'min:0', 'max:255'], 'is_active' => ['boolean']],
            'news' => ['title' => ['required', 'string', 'max:180'], 'slug' => ['required', 'string', 'max:200', ...$unique('slug', 'news')], 'summary' => ['nullable', 'string', 'max:500'], 'body' => ['required', 'string'], 'type' => ['required', 'string', 'max:20'], 'status' => ['required', 'string', 'max:20'], 'published_at' => ['nullable', 'date']],
        };
    }
    private function audit(Request $request, string $action, Model $model, ?array $before, ?array $after): void { \App\Models\AuditLog::create(['user_id' => $request->user()->id, 'action' => $action, 'entity_type' => $model->getTable(), 'entity_id' => $model->id, 'before_data' => $before, 'after_data' => $after, 'ip_address' => $request->ip()]); }
}
