<?php

namespace App\Enums;

enum SessionStatus: string
{
    case Draft = 'draft';
    case RegistrationOpen = 'registration_open';
    case RegistrationClosed = 'registration_closed';
    case Preselection = 'preselection';
    case Ready = 'ready';
    case InProgress = 'in_progress';
    case Paused = 'paused';
    case Ended = 'ended';
    case Cancelled = 'cancelled';
}
