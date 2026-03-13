<?php

namespace App\Game;

final readonly class MoveResult
{
    /**
     * @param array{player: string, pieceIndex: int, from: string, to: string}|null $kicked
     */
    public function __construct(
        public GameState $newState,
        public int $pieceIndex,
        public PiecePosition $from,
        public PiecePosition $to,
        public ?array $kicked = null,
        public bool $extraTurn = false,
        public ?PlayerColor $winner = null,
    ) {
    }
}
