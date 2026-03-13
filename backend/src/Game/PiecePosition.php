<?php

namespace App\Game;

final readonly class PiecePosition
{
    private function __construct(
        public string $type,
        public ?int $index = null,
    ) {
    }

    public static function base(): self
    {
        return new self('base');
    }

    public static function path(int $index): self
    {
        assert($index >= 0 && $index < 40, 'Path index must be 0-39');
        return new self('path', $index);
    }

    public static function goal(int $index): self
    {
        assert($index >= 0 && $index < 4, 'Goal index must be 0-3');
        return new self('goal', $index);
    }

    public static function finished(): self
    {
        return new self('finished');
    }

    public function isBase(): bool
    {
        return $this->type === 'base';
    }

    public function isPath(): bool
    {
        return $this->type === 'path';
    }

    public function isGoal(): bool
    {
        return $this->type === 'goal';
    }

    public function isFinished(): bool
    {
        return $this->type === 'finished';
    }

    public function isOnBoard(): bool
    {
        return $this->isPath() || $this->isGoal();
    }

    public function equals(self $other): bool
    {
        return $this->type === $other->type && $this->index === $other->index;
    }

    public function toString(): string
    {
        if ($this->index !== null) {
            return $this->type . ':' . $this->index;
        }

        return $this->type;
    }

    public static function fromString(string $str): self
    {
        if ($str === 'base') {
            return self::base();
        }
        if ($str === 'finished') {
            return self::finished();
        }

        $parts = explode(':', $str, 2);
        if (count($parts) !== 2) {
            throw new \InvalidArgumentException("Invalid position string: $str");
        }

        return match ($parts[0]) {
            'path' => self::path((int) $parts[1]),
            'goal' => self::goal((int) $parts[1]),
            default => throw new \InvalidArgumentException("Unknown position type: {$parts[0]}"),
        };
    }
}
