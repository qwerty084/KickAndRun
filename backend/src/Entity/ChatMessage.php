<?php

namespace App\Entity;

use App\Repository\ChatMessageRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ChatMessageRepository::class)]
#[ORM\Table(name: 'chat_message')]
#[ORM\Index(columns: ['context', 'context_id', 'created_at'], name: 'idx_chat_context')]
class ChatMessage
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(length: 500)]
    private string $content;

    #[ORM\ManyToOne(targetEntity: Player::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Player $player;

    /** lobby or game */
    #[ORM\Column(length: 10)]
    private string $context;

    #[ORM\Column(type: 'uuid')]
    private Uuid $contextId;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(Player $player, string $content, string $context, Uuid $contextId)
    {
        $this->id = Uuid::v7();
        $this->player = $player;
        $this->content = $content;
        $this->context = $context;
        $this->contextId = $contextId;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getPlayer(): Player
    {
        return $this->player;
    }

    public function getContext(): string
    {
        return $this->context;
    }

    public function getContextId(): Uuid
    {
        return $this->contextId;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
