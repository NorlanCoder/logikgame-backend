<?php

namespace App\Enums;

enum JackpotTransactionType: string
{
    case Elimination = 'elimination';
    case RoundSkip = 'round_skip';
    case Round6Bonus = 'round6_bonus';
    case Round6Departure = 'round6_departure';
    case FinaleWin = 'finale_win';
    case FinaleShare = 'finale_share';
    case FinaleAbandonShare = 'finale_abandon_share';
    case ManualAdjustment = 'manual_adjustment';
}
