<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\ChatMessageRepository;

#[ORM\Entity(repositoryClass: ChatMessageRepository::class)]
#[ORM\Table(name: 'chat_message')]
class ChatMessage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    #[ORM\ManyToOne(targetEntity: AnnonceSortie::class, inversedBy: 'chatMessages')]
    #[ORM\JoinColumn(name: 'annonce_id', referencedColumnName: 'id')]
    private ?AnnonceSortie $annonceSortie = null;

    public function getAnnonceSortie(): ?AnnonceSortie
    {
        return $this->annonceSortie;
    }

    public function setAnnonceSortie(?AnnonceSortie $annonceSortie): self
    {
        $this->annonceSortie = $annonceSortie;
        return $this;
    }

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'chatMessages')]
    #[ORM\JoinColumn(name: 'sender_id', referencedColumnName: 'id')]
    private ?User $user = null;

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    #[ORM\Column(type: 'text', nullable: false)]
    private ?string $content = null;

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $message_type = null;

    public function getMessage_type(): ?string
    {
        return $this->message_type;
    }

    public function setMessage_type(string $message_type): self
    {
        $this->message_type = $message_type;
        return $this;
    }

    #[ORM\ManyToOne(targetEntity: Poll::class, inversedBy: 'chatMessages')]
    #[ORM\JoinColumn(name: 'poll_id', referencedColumnName: 'id')]
    private ?Poll $poll = null;

    public function getPoll(): ?Poll
    {
        return $this->poll;
    }

    public function setPoll(?Poll $poll): self
    {
        $this->poll = $poll;
        return $this;
    }

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $meta_json = null;

    public function getMeta_json(): ?string
    {
        return $this->meta_json;
    }

    public function setMeta_json(?string $meta_json): self
    {
        $this->meta_json = $meta_json;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: false)]
    private ?\DateTimeInterface $sent_at = null;

    public function getSent_at(): ?\DateTimeInterface
    {
        return $this->sent_at;
    }

    public function setSent_at(\DateTimeInterface $sent_at): self
    {
        $this->sent_at = $sent_at;
        return $this;
    }

    public function getMessageType(): ?string
    {
        return $this->message_type;
    }

    public function setMessageType(string $message_type): static
    {
        $this->message_type = $message_type;

        return $this;
    }

    public function getMetaJson(): ?string
    {
        return $this->meta_json;
    }

    public function setMetaJson(?string $meta_json): static
    {
        $this->meta_json = $meta_json;

        return $this;
    }

    public function getSentAt(): ?\DateTime
    {
        return $this->sent_at;
    }

    public function setSentAt(\DateTime $sent_at): static
    {
        $this->sent_at = $sent_at;

        return $this;
    }

}
