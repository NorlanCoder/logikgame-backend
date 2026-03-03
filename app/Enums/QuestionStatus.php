<?php

namespace App\Enums;

enum QuestionStatus: string
{
    case Pending = 'pending';
    case Launched = 'launched';
    case Closed = 'closed';
    case Revealed = 'revealed';
}
