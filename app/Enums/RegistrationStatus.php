<?php

namespace App\Enums;

enum RegistrationStatus: string
{
    case Registered = 'registered';
    case PreselectionPending = 'preselection_pending';
    case PreselectionDone = 'preselection_done';
    case Selected = 'selected';
    case Rejected = 'rejected';
}
