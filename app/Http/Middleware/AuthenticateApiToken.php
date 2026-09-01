<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        if (! $token || ! Cache::has('api_token:'.hash('sha256', $token))) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        try {
            [$userId, $expiresAt, $nonce] = explode('|', Crypt::decryptString($token), 3);
            if (! ctype_digit($userId) || ! ctype_digit($expiresAt) || strlen($nonce) !== 40 || (int) $expiresAt < now()->timestamp || ! $user = User::query()->where('is_active', true)->find($userId)) {
                throw new \RuntimeException('Expired token');
            }
        } catch (\Throwable) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $request->setUserResolver(fn (): User => $user);
        return $next($request);
    }
}
