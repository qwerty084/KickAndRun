<?php

namespace App\Game;

enum PlayerColor: string
{
    case Green = 'green';
    case Yellow = 'yellow';
    case Red = 'red';
    case Black = 'black';

    public function entryPosition(): int
    {
        return match ($this) {
            self::Green => 0,
            self::Yellow => 10,
            self::Red => 20,
            self::Black => 30,
        };
    }

    public function goalEntryAfter(): int
    {
        return match ($this) {
            self::Green => 1,
            self::Yellow => 11,
            self::Red => 21,
            self::Black => 31,
        };
    }

    /**
     * @return list<self>
     */
    public static function inOrder(): array
    {
        return [self::Green, self::Yellow, self::Red, self::Black];
    }
}
