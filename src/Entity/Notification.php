<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\NotificationRepository;

#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\Table(name: 'notifications')]
class Notification
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

    #[ORM\OneToOne(targetEntity: User::class, inversedBy: 'notification')]
    #[ORM\JoinColumn(name: 'receiver_id', referencedColumnName: 'id', unique: true)]
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

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'notifications')]
    #[ORM\JoinColumn(name: 'sender_id', referencedColumnName: 'id')]
    private ?User $sender = null;

    public function getSender(): ?User
    {
        return $this->sender;
    }

    public function setSender(?User $sender): self
    {
        $this->sender = $sender;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $type = null;

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $title = null;

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    #[ORM\Column(type: 'text', nullable: false)]
    private ?string $body = null;

    public function getBody(): ?string
    {
        return $this->body;
    }

    public function setBody(string $body): self
    {
        $this->body = $body;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $entity_type = null;

    public function getEntity_type(): ?string
    {
        return $this->entity_type;
    }

    public function setEntity_type(string $entity_type): self
    {
        $this->entity_type = $entity_type;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: false)]
    private ?int $entity_id = null;

    public function getEntity_id(): ?int
    {
        return $this->entity_id;
    }

    public function setEntity_id(int $entity_id): self
    {
        $this->entity_id = $entity_id;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: false)]
    private ?\DateTimeInterface $created_at = null;

    public function getCreated_at(): ?\DateTimeInterface
    {
        return $this->created_at;
    }

    public function setCreated_at(\DateTimeInterface $created_at): self
    {
        $this->created_at = $created_at;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $read_at = null;

    public function getRead_at(): ?\DateTimeInterface
    {
        return $this->read_at;
    }

    public function setRead_at(?\DateTimeInterface $read_at): self
    {
        $this->read_at = $read_at;
        return $this;
    }

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $metadata_json = null;

    public function getMetadata_json(): ?string
    {
        return $this->metadata_json;
    }

    public function setMetadata_json(?string $metadata_json): self
    {
        $this->metadata_json = $metadata_json;
        return $this;
    }

    public function getEntityType(): ?string
    {
        return $this->entity_type;
    }

    public function setEntityType(string $entity_type): static
    {
        $this->entity_type = $entity_type;

        return $this;
    }

    public function getEntityId(): ?int
    {
        return $this->entity_id;
    }

    public function setEntityId(int $entity_id): static
    {
        $this->entity_id = $entity_id;

        return $this;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTime $created_at): static
    {
        $this->created_at = $created_at;

        return $this;
    }

    public function getReadAt(): ?\DateTime
    {
        return $this->read_at;
    }

    public function setReadAt(?\DateTime $read_at): static
    {
        $this->read_at = $read_at;

        return $this;
    }

    public function getMetadataJson(): ?string
    {
        return $this->metadata_json;
    }

    public function setMetadataJson(?string $metadata_json): static
    {
        $this->metadata_json = $metadata_json;

        return $this;
    }

}
