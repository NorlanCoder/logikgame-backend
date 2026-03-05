<?php

namespace App\Enums;

enum MediaType: string
{
    case None = 'none';
    case Image = 'image';
    case Video = 'video';
    case Audio = 'audio';
}
