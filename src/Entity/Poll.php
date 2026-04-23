<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\PollRepository;

#[ORM\Entity(repositoryClass: PollRepository::class)]
#[ORM\Table(name: 'poll')]
class Poll
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

    #[ORM\ManyToOne(targetEntity: AnnonceSortie::class, inversedBy: 'polls')]
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

    #[ORM\Column(type: 'text', nullable: false)]
    private ?string $question = null;

    public function getQuestion(): ?string
    {
        return $this->question;
    }

    public function setQuestion(string $question): self
    {
        $this->question = $question;
        return $this;
    }

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'polls')]
    #[ORM\JoinColumn(name: 'created_by', referencedColumnName: 'id')]
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

    #[ORM\Column(type: 'boolean', nullable: false)]
    private ?bool $is_open = null;

    public function is_open(): ?bool
    {
        return $this->is_open;
    }

    public function setIs_open(bool $is_open): self
    {
        $this->is_open = $is_open;
        return $this;
    }

    #[ORM\Column(type: 'boolean', nullable: false)]
    private ?bool $allow_multi = null;

    public function isAllow_multi(): ?bool
    {
        return $this->allow_multi;
    }

    public function setAllow_multi(bool $allow_multi): self
    {
        $this->allow_multi = $allow_multi;
        return $this;
    }

    #[ORM\Column(type: 'boolean', nullable: false)]
    private ?bool $allow_add_options = null;

    public function isAllow_add_options(): ?bool
    {
        return $this->allow_add_options;
    }

    public function setAllow_add_options(bool $allow_add_options): self
    {
        $this->allow_add_options = $allow_add_options;
        return $this;
    }

    #[ORM\Column(type: 'boolean', nullable: false)]
    private ?bool $is_pinned = null;

    public function is_pinned(): ?bool
    {
        return $this->is_pinned;
    }

    public function setIs_pinned(bool $is_pinned): self
    {
        $this->is_pinned = $is_pinned;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $closed_at = null;

    public function getClosed_at(): ?\DateTimeInterface
    {
        return $this->closed_at;
    }

    public function setClosed_at(?\DateTimeInterface $closed_at): self
    {
        $this->closed_at = $closed_at;
        return $this;
    }

    #[ORM\OneToMany(targetEntity: ChatMessage::class, mappedBy: 'poll')]
    private Collection $chatMessages;

    /**
     * @return Collection<int, ChatMessage>
     */
    public function getChatMessages(): Collection
    {
        if (!$this->chatMessages instanceof Collection) {
            $this->chatMessages = new ArrayCollection();
        }
        return $this->chatMessages;
    }

    public function addChatMessage(ChatMessage $chatMessage): self
    {
        if (!$this->getChatMessages()->contains($chatMessage)) {
            $this->getChatMessages()->add($chatMessage);
        }
        return $this;
    }

    public function removeChatMessage(ChatMessage $chatMessage): self
    {
        $this->getChatMessages()->removeElement($chatMessage);
        return $this;
    }

    #[ORM\OneToMany(targetEntity: PollOption::class, mappedBy: 'poll')]
    private Collection $pollOptions;

    public function __construct()
    {
        $this->chatMessages = new ArrayCollection();
        $this->pollOptions = new ArrayCollection();
    }

    /**
     * @return Collection<int, PollOption>
     */
    public function getPollOptions(): Collection
    {
        if (!$this->pollOptions instanceof Collection) {
            $this->pollOptions = new ArrayCollection();
        }
        return $this->pollOptions;
    }

    public function addPollOption(PollOption $pollOption): self
    {
        if (!$this->getPollOptions()->contains($pollOption)) {
            $this->getPollOptions()->add($pollOption);
        }
        return $this;
    }

    public function removePollOption(PollOption $pollOption): self
    {
        $this->getPollOptions()->removeElement($pollOption);
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

    public function isOpen(): ?bool
    {
        return $this->is_open;
    }

    public function setIsOpen(bool $is_open): static
    {
        $this->is_open = $is_open;

        return $this;
    }

    public function isAllowMulti(): ?bool
    {
        return $this->allow_multi;
    }

    public function setAllowMulti(bool $allow_multi): static
    {
        $this->allow_multi = $allow_multi;

        return $this;
    }

    public function isAllowAddOptions(): ?bool
    {
        return $this->allow_add_options;
    }

    public function setAllowAddOptions(bool $allow_add_options): static
    {
        $this->allow_add_options = $allow_add_options;

        return $this;
    }

    public function isPinned(): ?bool
    {
        return $this->is_pinned;
    }

    public function setIsPinned(bool $is_pinned): static
    {
        $this->is_pinned = $is_pinned;

        return $this;
    }

    public function getClosedAt(): ?\DateTime
    {
        return $this->closed_at;
    }

    public function setClosedAt(?\DateTime $closed_at): static
    {
        $this->closed_at = $closed_at;

        return $this;
    }

}
