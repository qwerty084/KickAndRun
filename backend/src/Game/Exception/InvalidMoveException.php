<?php

namespace App\Game\Exception;

class InvalidMoveException extends \RuntimeException
{
    public function __construct(string $message = 'Invalid move.')
    {
        parent::__construct($message);
    }
}
