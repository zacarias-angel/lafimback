<?php

namespace App\Http\Middleware;

use App\Models\Club;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureClubAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $club = $request->route('club');
        $clubId = $club instanceof Club ? $club->id : $club;
        if (! $request->user()->hasRole('SUPER_ADMIN') && ! $request->user()->clubs()->whereKey($clubId)->exists()) {
            return response()->json(['message' => 'You are not assigned to this club.'], 403);
        }
        return $next($request);
    }
}
