<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\UserPointRepository;

#[ORM\Entity(repositoryClass: UserPointRepository::class)]
#[ORM\Table(name: 'user_points')]
class UserPoint
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

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'userPoints')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id')]
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

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $total_points = null;

    public function getTotal_points(): ?int
    {
        return $this->total_points;
    }

    public function setTotal_points(?int $total_points): self
    {
        $this->total_points = $total_points;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $nb_lieux_visites = null;

    public function getNb_lieux_visites(): ?int
    {
        return $this->nb_lieux_visites;
    }

    public function setNb_lieux_visites(?int $nb_lieux_visites): self
    {
        $this->nb_lieux_visites = $nb_lieux_visites;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $nb_avis_laisses = null;

    public function getNb_avis_laisses(): ?int
    {
        return $this->nb_avis_laisses;
    }

    public function setNb_avis_laisses(?int $nb_avis_laisses): self
    {
        $this->nb_avis_laisses = $nb_avis_laisses;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $nb_favoris = null;

    public function getNb_favoris(): ?int
    {
        return $this->nb_favoris;
    }

    public function setNb_favoris(?int $nb_favoris): self
    {
        $this->nb_favoris = $nb_favoris;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $nb_sorties_jointes = null;

    public function getNb_sorties_jointes(): ?int
    {
        return $this->nb_sorties_jointes;
    }

    public function setNb_sorties_jointes(?int $nb_sorties_jointes): self
    {
        $this->nb_sorties_jointes = $nb_sorties_jointes;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: false)]
    private ?\DateTimeInterface $updated_at = null;

    public function getUpdated_at(): ?\DateTimeInterface
    {
        return $this->updated_at;
    }

    public function setUpdated_at(\DateTimeInterface $updated_at): self
    {
        $this->updated_at = $updated_at;
        return $this;
    }

    public function getTotalPoints(): ?int
    {
        return $this->total_points;
    }

    public function setTotalPoints(?int $total_points): static
    {
        $this->total_points = $total_points;

        return $this;
    }

    public function getNbLieuxVisites(): ?int
    {
        return $this->nb_lieux_visites;
    }

    public function setNbLieuxVisites(?int $nb_lieux_visites): static
    {
        $this->nb_lieux_visites = $nb_lieux_visites;

        return $this;
    }

    public function getNbAvisLaisses(): ?int
    {
        return $this->nb_avis_laisses;
    }

    public function setNbAvisLaisses(?int $nb_avis_laisses): static
    {
        $this->nb_avis_laisses = $nb_avis_laisses;

        return $this;
    }

    public function getNbFavoris(): ?int
    {
        return $this->nb_favoris;
    }

    public function setNbFavoris(?int $nb_favoris): static
    {
        $this->nb_favoris = $nb_favoris;

        return $this;
    }

    public function getNbSortiesJointes(): ?int
    {
        return $this->nb_sorties_jointes;
    }

    public function setNbSortiesJointes(?int $nb_sorties_jointes): static
    {
        $this->nb_sorties_jointes = $nb_sorties_jointes;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updated_at;
    }

    public function setUpdatedAt(\DateTime $updated_at): static
    {
        $this->updated_at = $updated_at;

        return $this;
    }

}
