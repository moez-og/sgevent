<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\BadgeRepository;

#[ORM\Entity(repositoryClass: BadgeRepository::class)]
#[ORM\Table(name: 'badge')]
class Badge
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

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $code = null;

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $nom = null;

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): self
    {
        $this->nom = $nom;
        return $this;
    }

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $emoji = null;

    public function getEmoji(): ?string
    {
        return $this->emoji;
    }

    public function setEmoji(?string $emoji): self
    {
        $this->emoji = $emoji;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $categorie = null;

    public function getCategorie(): ?string
    {
        return $this->categorie;
    }

    public function setCategorie(?string $categorie): self
    {
        $this->categorie = $categorie;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $seuil_requis = null;

    public function getSeuil_requis(): ?int
    {
        return $this->seuil_requis;
    }

    public function setSeuil_requis(?int $seuil_requis): self
    {
        $this->seuil_requis = $seuil_requis;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $points_bonus = null;

    public function getPoints_bonus(): ?int
    {
        return $this->points_bonus;
    }

    public function setPoints_bonus(?int $points_bonus): self
    {
        $this->points_bonus = $points_bonus;
        return $this;
    }

    #[ORM\OneToOne(targetEntity: BadgeRecompense::class, mappedBy: 'badge')]
    private ?BadgeRecompense $badgeRecompense = null;

    public function getBadgeRecompense(): ?BadgeRecompense
    {
        return $this->badgeRecompense;
    }

    public function setBadgeRecompense(?BadgeRecompense $badgeRecompense): self
    {
        $this->badgeRecompense = $badgeRecompense;
        return $this;
    }

    #[ORM\OneToOne(targetEntity: OffreBadgeUser::class, mappedBy: 'badge')]
    private ?OffreBadgeUser $offreBadgeUser = null;

    public function getOffreBadgeUser(): ?OffreBadgeUser
    {
        return $this->offreBadgeUser;
    }

    public function setOffreBadgeUser(?OffreBadgeUser $offreBadgeUser): self
    {
        $this->offreBadgeUser = $offreBadgeUser;
        return $this;
    }

    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'badges')]
    #[ORM\JoinTable(
        name: 'user_badge',
        joinColumns: [
            new ORM\JoinColumn(name: 'badge_id', referencedColumnName: 'id')
        ],
        inverseJoinColumns: [
            new ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id')
        ]
    )]
    private Collection $users;

    public function __construct()
    {
        $this->users = new ArrayCollection();
    }

    /**
     * @return Collection<int, User>
     */
    public function getUsers(): Collection
    {
        if (!$this->users instanceof Collection) {
            $this->users = new ArrayCollection();
        }
        return $this->users;
    }

    public function addUser(User $user): self
    {
        if (!$this->getUsers()->contains($user)) {
            $this->getUsers()->add($user);
        }
        return $this;
    }

    public function removeUser(User $user): self
    {
        $this->getUsers()->removeElement($user);
        return $this;
    }

    public function getSeuilRequis(): ?int
    {
        return $this->seuil_requis;
    }

    public function setSeuilRequis(?int $seuil_requis): static
    {
        $this->seuil_requis = $seuil_requis;

        return $this;
    }

    public function getPointsBonus(): ?int
    {
        return $this->points_bonus;
    }

    public function setPointsBonus(?int $points_bonus): static
    {
        $this->points_bonus = $points_bonus;

        return $this;
    }

}
