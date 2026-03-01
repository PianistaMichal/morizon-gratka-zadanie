<?php

declare(strict_types=1);

namespace App\Exception;

class UserNotFoundException extends \RuntimeException
{
    public function __construct(string $username)
    {
        parent::__construct("User '{$username}' not found", 404);
    }
}
