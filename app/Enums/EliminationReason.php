<?php

namespace App\Enums;

enum EliminationReason: string
{
    case WrongAnswer = 'wrong_answer';
    case Timeout = 'timeout';
    case SecondChanceFailed = 'second_chance_failed';
    case RoundSkip = 'round_skip';
    case Top4Cutoff = 'top4_cutoff';
    case DuelLost = 'duel_lost';
    case FinaleLost = 'finale_lost';
    case Manual = 'manual';
}
