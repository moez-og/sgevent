<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\ParticipationAnnonceRepository;

#[ORM\Entity(repositoryClass: ParticipationAnnonceRepository::class)]
#[ORM\Table(name: 'participation_annonce')]
class ParticipationAnnonce
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

    #[ORM\OneToOne(targetEntity: AnnonceSortie::class, inversedBy: 'participationAnnonce')]
    #[ORM\JoinColumn(name: 'annonce_id', referencedColumnName: 'id', unique: true)]
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

    #[ORM\OneToOne(targetEntity: User::class, inversedBy: 'participationAnnonce')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', unique: true)]
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
    private ?\DateTimeInterface $date_demande = null;

    public function getDate_demande(): ?\DateTimeInterface
    {
        return $this->date_demande;
    }

    public function setDate_demande(\DateTimeInterface $date_demande): self
    {
        $this->date_demande = $date_demande;
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

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $contact_prefer = null;

    public function getContact_prefer(): ?string
    {
        return $this->contact_prefer;
    }

    public function setContact_prefer(string $contact_prefer): self
    {
        $this->contact_prefer = $contact_prefer;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $contact_value = null;

    public function getContact_value(): ?string
    {
        return $this->contact_value;
    }

    public function setContact_value(?string $contact_value): self
    {
        $this->contact_value = $contact_value;
        return $this;
    }

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $commentaire = null;

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): self
    {
        $this->commentaire = $commentaire;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: false)]
    private ?int $nb_places = null;

    public function getNb_places(): ?int
    {
        return $this->nb_places;
    }

    public function setNb_places(int $nb_places): self
    {
        $this->nb_places = $nb_places;
        return $this;
    }

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $reponses_json = null;

    public function getReponses_json(): ?string
    {
        return $this->reponses_json;
    }

    public function setReponses_json(?string $reponses_json): self
    {
        $this->reponses_json = $reponses_json;
        return $this;
    }

    public function getDateDemande(): ?\DateTime
    {
        return $this->date_demande;
    }

    public function setDateDemande(\DateTime $date_demande): static
    {
        $this->date_demande = $date_demande;

        return $this;
    }

    public function getContactPrefer(): ?string
    {
        return $this->contact_prefer;
    }

    public function setContactPrefer(string $contact_prefer): static
    {
        $this->contact_prefer = $contact_prefer;

        return $this;
    }

    public function getContactValue(): ?string
    {
        return $this->contact_value;
    }

    public function setContactValue(?string $contact_value): static
    {
        $this->contact_value = $contact_value;

        return $this;
    }

    public function getNbPlaces(): ?int
    {
        return $this->nb_places;
    }

    public function setNbPlaces(int $nb_places): static
    {
        $this->nb_places = $nb_places;

        return $this;
    }

    public function getReponsesJson(): ?string
    {
        return $this->reponses_json;
    }

    public function setReponsesJson(?string $reponses_json): static
    {
        $this->reponses_json = $reponses_json;

        return $this;
    }

}
