<?php

namespace App\Game\Exception;

class NotYourTurnException extends \RuntimeException
{
    public function __construct(string $message = 'It is not your turn.')
    {
        parent::__construct($message);
    }
}
