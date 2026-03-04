<?php

namespace App\Enums;

enum FinaleScenario: string
{
    case BothContinueBothWin = 'both_continue_both_win';
    case BothContinueOneWins = 'both_continue_one_wins';
    case BothContinueBothFail = 'both_continue_both_fail';
    case OneAbandons = 'one_abandons';
    case BothAbandon = 'both_abandon';
}
