<?php

namespace App\Enums;

enum SessionPlayerStatus: string
{
    case Waiting = 'waiting';
    case Active = 'active';
    case Eliminated = 'eliminated';
    case Finalist = 'finalist';
    case FinalistWinner = 'finalist_winner';
    case FinalistLoser = 'finalist_loser';
    case Abandoned = 'abandoned';
}
