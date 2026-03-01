<?php

declare(strict_types=1);

namespace App\Enum;

enum FlashType: string
{
    case SUCCESS = 'success';
    case ERROR = 'error';
    case INFO = 'info';
}
