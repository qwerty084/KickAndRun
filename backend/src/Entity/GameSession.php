<?php

namespace App\Entity;

use App\Repository\GameSessionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: GameSessionRepository::class)]
#[ORM\Table(name: 'game_session')]
class GameSession
{
    public const STATUS_CREATED = 'created';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_FINISHED = 'finished';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\OneToOne(targetEntity: Lobby::class, inversedBy: 'gameSession')]
    #[ORM\JoinColumn(nullable: false)]
    private Lobby $lobby;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_CREATED;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $gameState = [];

    #[ORM\Column]
    private int $currentTurn = 0;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(Lobby $lobby)
    {
        $this->id = Uuid::v7();
        $this->lobby = $lobby;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getLobby(): Lobby
    {
        return $this->lobby;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    /** @return array<string, mixed> */
    public function getGameState(): array
    {
        return $this->gameState;
    }

    /** @param array<string, mixed> $gameState */
    public function setGameState(array $gameState): static
    {
        $this->gameState = $gameState;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getCurrentTurn(): int
    {
        return $this->currentTurn;
    }

    public function setCurrentTurn(int $currentTurn): static
    {
        $this->currentTurn = $currentTurn;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
