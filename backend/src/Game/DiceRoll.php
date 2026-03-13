<?php

namespace App\Game;

final readonly class DiceRoll
{
    public function __construct(
        public int $value,
    ) {
        assert($value >= 1 && $value <= 6, 'Dice roll must be 1-6');
    }

    public function isSix(): bool
    {
        return $this->value === 6;
    }
}
