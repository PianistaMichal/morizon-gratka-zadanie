<?php

declare(strict_types=1);

namespace App\Exception;

use RuntimeException;

class InvalidTokenException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Invalid token', 401);
    }
}
