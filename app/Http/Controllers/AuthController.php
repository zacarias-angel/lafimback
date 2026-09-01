<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(LoginRequest $request)
    {
        $user = User::query()->where('email', $request->validated('email'))->where('is_active', true)->first();
        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 422);
        }
        $expiry = now()->addHours(12);
        $token = Crypt::encryptString($user->id.'|'.$expiry->timestamp.'|'.Str::random(40));
        Cache::put('api_token:'.hash('sha256', $token), true, $expiry);
        $user->forceFill(['last_login_at' => now()])->save();
        return response()->json(['token' => $token, 'token_type' => 'Bearer', 'expires_at' => $expiry->toIso8601String(), 'user' => $user->load('roles', 'clubs')]);
    }

    public function logout(Request $request)
    {
        Cache::forget('api_token:'.hash('sha256', $request->bearerToken()));
        return response()->noContent();
    }

    public function me(Request $request) { return response()->json($request->user()->load('roles', 'clubs')); }
}
