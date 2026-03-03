<?php

namespace App\Enums;

enum AnswerType: string
{
    case Qcm = 'qcm';
    case Number = 'number';
    case Text = 'text';
}
