<?php

namespace App\Entity;

use App\Repository\LobbyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: LobbyRepository::class)]
#[ORM\Table(name: 'lobby')]
class Lobby
{
    public const STATUS_WAITING = 'waiting';
    public const STATUS_IN_GAME = 'in_game';
    public const STATUS_FINISHED = 'finished';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(length: 6, unique: true)]
    private string $code;

    #[ORM\Column(length: 128)]
    private string $name;

    #[ORM\ManyToOne(targetEntity: Player::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Player $hostPlayer;

    /** @var Collection<int, Player> */
    #[ORM\ManyToMany(targetEntity: Player::class)]
    #[ORM\JoinTable(name: 'lobby_player')]
    private Collection $players;

    #[ORM\Column]
    private int $maxPlayers = 4;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_WAITING;

    #[ORM\OneToOne(mappedBy: 'lobby', targetEntity: GameSession::class)]
    private ?GameSession $gameSession = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $name, Player $hostPlayer)
    {
        $this->id = Uuid::v7();
        $this->code = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        $this->name = $name;
        $this->hostPlayer = $hostPlayer;
        $this->players = new ArrayCollection([$hostPlayer]);
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getHostPlayer(): Player
    {
        return $this->hostPlayer;
    }

    /** @return Collection<int, Player> */
    public function getPlayers(): Collection
    {
        return $this->players;
    }

    public function addPlayer(Player $player): static
    {
        if (!$this->players->contains($player)) {
            $this->players->add($player);
            $this->updatedAt = new \DateTimeImmutable();
        }

        return $this;
    }

    public function removePlayer(Player $player): static
    {
        $this->players->removeElement($player);
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getMaxPlayers(): int
    {
        return $this->maxPlayers;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getGameSession(): ?GameSession
    {
        return $this->gameSession;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function isFull(): bool
    {
        return $this->players->count() >= $this->maxPlayers;
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
