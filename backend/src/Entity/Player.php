<?php

namespace App\Entity;

use App\Repository\PlayerRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: PlayerRepository::class)]
#[ORM\Table(name: 'player')]
class Player
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(length: 64)]
    private string $name;

    #[ORM\Column(options: ['default' => false])]
    private bool $isBot = false;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $name, bool $isBot = false)
    {
        $this->id = Uuid::v7();
        $this->name = $name;
        $this->isBot = $isBot;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isBot(): bool
    {
        return $this->isBot;
    }
}
