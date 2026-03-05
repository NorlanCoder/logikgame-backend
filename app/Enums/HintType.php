<?php

namespace App\Enums;

enum HintType: string
{
    case RemoveChoices = 'remove_choices';
    case RevealLetters = 'reveal_letters';
    case ReduceRange = 'reduce_range';
}
