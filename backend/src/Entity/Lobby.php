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

    /** @var Collection<int, GameSession> */
    #[ORM\OneToMany(mappedBy: 'lobby', targetEntity: GameSession::class)]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $gameSessions;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    /** @var list<string>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $playerOrder = null;

    public function __construct(string $name, Player $hostPlayer)
    {
        $this->id = Uuid::v7();
        $this->code = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        $this->name = $name;
        $this->hostPlayer = $hostPlayer;
        $this->players = new ArrayCollection([$hostPlayer]);
        $this->gameSessions = new ArrayCollection();
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

    /** @return Collection<int, GameSession> */
    public function getGameSessions(): Collection
    {
        return $this->gameSessions;
    }

    public function getLatestGameSession(): ?GameSession
    {
        if ($this->gameSessions->isEmpty()) {
            return null;
        }

        return $this->gameSessions->first() ?: null;
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

    /** @return list<string>|null */
    public function getPlayerOrder(): ?array
    {
        return $this->playerOrder;
    }

    /**
     * Swap two player positions by their UUIDs (as RFC4122 strings).
     * If playerOrder has not been set yet, initialise it from the current collection order.
     */
    public function swapPlayerColors(string $playerIdA, string $playerIdB): void
    {
        $players = $this->players->toArray();
        $currentOrder = $this->playerOrder ?? array_map(
            fn (Player $p) => $p->getId()->toRfc4122(),
            $players,
        );

        $indexA = array_search($playerIdA, $currentOrder, true);
        $indexB = array_search($playerIdB, $currentOrder, true);

        if ($indexA === false || $indexB === false) {
            throw new \InvalidArgumentException('One or both players not found in lobby.');
        }

        [$currentOrder[$indexA], $currentOrder[$indexB]] = [$currentOrder[$indexB], $currentOrder[$indexA]];
        $this->playerOrder = array_values($currentOrder);
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Return players sorted by playerOrder (if set), otherwise in collection order.
     *
     * @return list<Player>
     */
    public function getOrderedPlayers(): array
    {
        $players = $this->players->toArray();

        if ($this->playerOrder === null) {
            return array_values($players);
        }

        $map = [];
        foreach ($players as $player) {
            $map[$player->getId()->toRfc4122()] = $player;
        }

        $ordered = [];
        foreach ($this->playerOrder as $id) {
            if (isset($map[$id])) {
                $ordered[] = $map[$id];
            }
        }

        // Append any players not in playerOrder (e.g. newly joined)
        foreach ($players as $player) {
            if (!in_array($player->getId()->toRfc4122(), $this->playerOrder, true)) {
                $ordered[] = $player;
            }
        }

        return array_values($ordered);
    }
}
