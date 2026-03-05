<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Channels publics : session.{id} — tout client connecté y a accès.
| Channels privés : player.{id} — seul le joueur concerné.
|
*/

// Canal privé pour un joueur spécifique
Broadcast::channel('player.{sessionPlayerId}', function ($user, int $sessionPlayerId) {
    // L'authentification des joueurs se fait via X-Player-Token
    // Ce canal sera autorisé côté middleware
    return true;
});
