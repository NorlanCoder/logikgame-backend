<?php

namespace App\Enums;

enum ConnectionEvent: string
{
    case Connected = 'connected';
    case Disconnected = 'disconnected';
    case Reconnected = 'reconnected';
}
