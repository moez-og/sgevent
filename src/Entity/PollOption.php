<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\PollOptionRepository;

#[ORM\Entity(repositoryClass: PollOptionRepository::class)]
#[ORM\Table(name: 'poll_option')]
class PollOption
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

    #[ORM\ManyToOne(targetEntity: Poll::class, inversedBy: 'pollOptions')]
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

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $text = null;

    public function getText(): ?string
    {
        return $this->text;
    }

    public function setText(string $text): self
    {
        $this->text = $text;
        return $this;
    }

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'pollOptions')]
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

    #[ORM\ManyToMany(targetEntity: Poll::class, inversedBy: 'pollOptions')]
    #[ORM\JoinTable(
        name: 'poll_vote',
        joinColumns: [
            new ORM\JoinColumn(name: 'option_id', referencedColumnName: 'id')
        ],
        inverseJoinColumns: [
            new ORM\JoinColumn(name: 'poll_id', referencedColumnName: 'id')
        ]
    )]
    private Collection $polls;

    public function __construct()
    {
        $this->polls = new ArrayCollection();
    }

    /**
     * @return Collection<int, Poll>
     */
    public function getPolls(): Collection
    {
        if (!$this->polls instanceof Collection) {
            $this->polls = new ArrayCollection();
        }
        return $this->polls;
    }

    public function addPoll(Poll $poll): self
    {
        if (!$this->getPolls()->contains($poll)) {
            $this->getPolls()->add($poll);
        }
        return $this;
    }

    public function removePoll(Poll $poll): self
    {
        $this->getPolls()->removeElement($poll);
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

}
