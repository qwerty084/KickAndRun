<?php

namespace App\Game;

final class GameState
{
    public const string PHASE_ROLLING = 'rolling';
    public const string PHASE_MOVING = 'moving';
    public const string PHASE_FINISHED = 'finished';

    /**
     * @param list<PlayerColor> $players
     * @param array<string, list<PiecePosition>> $pieces  Keyed by PlayerColor value
     */
    public function __construct(
        public readonly array $players,
        public int $currentPlayerIndex,
        public array $pieces,
        public ?int $lastDiceRoll,
        public string $phase,
        public int $consecutiveSixes,
        public int $turnNumber,
        public ?PlayerColor $winner,
        public int $rollAttemptsLeft,
    ) {
    }

    public function currentPlayer(): PlayerColor
    {
        return $this->players[$this->currentPlayerIndex];
    }

    /**
     * @return list<PiecePosition>
     */
    public function piecesOf(PlayerColor $color): array
    {
        return $this->pieces[$color->value];
    }

    public function setPiece(PlayerColor $color, int $index, PiecePosition $position): void
    {
        $this->pieces[$color->value][$index] = $position;
    }

    public function advanceToNextPlayer(): void
    {
        $this->currentPlayerIndex = ($this->currentPlayerIndex + 1) % count($this->players);
        $this->consecutiveSixes = 0;
        $this->lastDiceRoll = null;
        $this->phase = self::PHASE_ROLLING;
        $this->rollAttemptsLeft = 0;
        $this->turnNumber++;
    }

    public function clone(): self
    {
        $clonedPieces = [];
        foreach ($this->pieces as $color => $positions) {
            $clonedPieces[$color] = $positions; // PiecePosition is readonly, shallow copy is fine
        }

        return new self(
            players: $this->players,
            currentPlayerIndex: $this->currentPlayerIndex,
            pieces: $clonedPieces,
            lastDiceRoll: $this->lastDiceRoll,
            phase: $this->phase,
            consecutiveSixes: $this->consecutiveSixes,
            turnNumber: $this->turnNumber,
            winner: $this->winner,
            rollAttemptsLeft: $this->rollAttemptsLeft,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $piecesArray = [];
        foreach ($this->pieces as $color => $positions) {
            $piecesArray[$color] = array_map(
                fn(PiecePosition $p) => ['position' => $p->toString()],
                $positions,
            );
        }

        return [
            'players' => array_map(fn(PlayerColor $c) => $c->value, $this->players),
            'currentPlayerIndex' => $this->currentPlayerIndex,
            'pieces' => $piecesArray,
            'lastDiceRoll' => $this->lastDiceRoll,
            'phase' => $this->phase,
            'consecutiveSixes' => $this->consecutiveSixes,
            'turnNumber' => $this->turnNumber,
            'winner' => $this->winner?->value,
            'rollAttemptsLeft' => $this->rollAttemptsLeft,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $players = array_map(
            fn(string $c) => PlayerColor::from($c),
            $data['players'],
        );

        $pieces = [];
        foreach ($data['pieces'] as $color => $positions) {
            $pieces[$color] = array_map(
                fn(array $p) => PiecePosition::fromString($p['position']),
                $positions,
            );
        }

        return new self(
            players: $players,
            currentPlayerIndex: $data['currentPlayerIndex'],
            pieces: $pieces,
            lastDiceRoll: $data['lastDiceRoll'],
            phase: $data['phase'],
            consecutiveSixes: $data['consecutiveSixes'],
            turnNumber: $data['turnNumber'],
            winner: isset($data['winner']) ? PlayerColor::from($data['winner']) : null,
            rollAttemptsLeft: $data['rollAttemptsLeft'] ?? 0,
        );
    }
}
