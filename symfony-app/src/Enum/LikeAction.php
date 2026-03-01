<?php

declare(strict_types=1);

namespace App\Enum;

enum LikeAction: string
{
    case LIKED = 'liked';
    case UNLIKED = 'unliked';
}
