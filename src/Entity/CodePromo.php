<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\CodePromoRepository;

#[ORM\Entity(repositoryClass: CodePromoRepository::class)]
#[ORM\Table(name: 'code_promo')]
class CodePromo
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

    #[ORM\ManyToOne(targetEntity: Offre::class, inversedBy: 'codePromos')]
    #[ORM\JoinColumn(name: 'offre_id', referencedColumnName: 'id')]
    private ?Offre $offre = null;

    public function getOffre(): ?Offre
    {
        return $this->offre;
    }

    public function setOffre(?Offre $offre): self
    {
        $this->offre = $offre;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $qr_image_url = null;

    public function getQr_image_url(): ?string
    {
        return $this->qr_image_url;
    }

    public function setQr_image_url(string $qr_image_url): self
    {
        $this->qr_image_url = $qr_image_url;
        return $this;
    }

    #[ORM\Column(type: 'date', nullable: false)]
    private ?\DateTimeInterface $date_generation = null;

    public function getDate_generation(): ?\DateTimeInterface
    {
        return $this->date_generation;
    }

    public function setDate_generation(\DateTimeInterface $date_generation): self
    {
        $this->date_generation = $date_generation;
        return $this;
    }

    #[ORM\Column(type: 'date', nullable: false)]
    private ?\DateTimeInterface $date_expiration = null;

    public function getDate_expiration(): ?\DateTimeInterface
    {
        return $this->date_expiration;
    }

    public function setDate_expiration(\DateTimeInterface $date_expiration): self
    {
        $this->date_expiration = $date_expiration;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $statut = null;

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): self
    {
        $this->statut = $statut;
        return $this;
    }

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'codePromos')]
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

    public function getQrImageUrl(): ?string
    {
        return $this->qr_image_url;
    }

    public function setQrImageUrl(string $qr_image_url): static
    {
        $this->qr_image_url = $qr_image_url;

        return $this;
    }

    public function getDateGeneration(): ?\DateTime
    {
        return $this->date_generation;
    }

    public function setDateGeneration(\DateTime $date_generation): static
    {
        $this->date_generation = $date_generation;

        return $this;
    }

    public function getDateExpiration(): ?\DateTime
    {
        return $this->date_expiration;
    }

    public function setDateExpiration(\DateTime $date_expiration): static
    {
        $this->date_expiration = $date_expiration;

        return $this;
    }

}
