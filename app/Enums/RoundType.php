<?php

namespace App\Enums;

enum RoundType: string
{
    case SuddenDeath = 'sudden_death';
    case Hint = 'hint';
    case SecondChance = 'second_chance';
    case RoundSkip = 'round_skip';
    case Top4Elimination = 'top4_elimination';
    case DuelJackpot = 'duel_jackpot';
    case DuelElimination = 'duel_elimination';
    case Finale = 'finale';
}
