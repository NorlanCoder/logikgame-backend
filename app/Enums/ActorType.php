<?php

namespace App\Enums;

enum ActorType: string
{
    case System = 'system';
    case Admin = 'admin';
    case Player = 'player';
}
