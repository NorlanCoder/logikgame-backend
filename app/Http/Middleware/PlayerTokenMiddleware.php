<?php

namespace App\Http\Middleware;

use App\Models\SessionPlayer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PlayerTokenMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('X-Player-Token') ?? $request->input('access_token');

        if (! $token) {
            return response()->json(['message' => 'Token joueur requis.'], 401);
        }

        $sessionPlayer = SessionPlayer::query()
            ->where('access_token', $token)
            ->with('session')
            ->first();

        if (! $sessionPlayer) {
            return response()->json(['message' => 'Token joueur invalide.'], 401);
        }

        $request->merge(['_session_player' => $sessionPlayer]);
        $request->attributes->set('session_player', $sessionPlayer);

        return $next($request);
    }
}
