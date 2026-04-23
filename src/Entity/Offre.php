<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\OffreRepository;

#[ORM\Entity(repositoryClass: OffreRepository::class)]
#[ORM\Table(name: 'offre')]
class Offre
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

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'offres')]
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

    #[ORM\ManyToOne(targetEntity: Evenement::class, inversedBy: 'offres')]
    #[ORM\JoinColumn(name: 'event_id', referencedColumnName: 'id')]
    private ?Evenement $evenement = null;

    public function getEvenement(): ?Evenement
    {
        return $this->evenement;
    }

    public function setEvenement(?Evenement $evenement): self
    {
        $this->evenement = $evenement;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $titre = null;

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): self
    {
        $this->titre = $titre;
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

    #[ORM\Column(type: 'float', nullable: false)]
    private ?float $pourcentage = null;

    public function getPourcentage(): ?float
    {
        return $this->pourcentage;
    }

    public function setPourcentage(float $pourcentage): self
    {
        $this->pourcentage = $pourcentage;
        return $this;
    }

    #[ORM\Column(type: 'date', nullable: false)]
    private ?\DateTimeInterface $date_debut = null;

    public function getDate_debut(): ?\DateTimeInterface
    {
        return $this->date_debut;
    }

    public function setDate_debut(\DateTimeInterface $date_debut): self
    {
        $this->date_debut = $date_debut;
        return $this;
    }

    #[ORM\Column(type: 'date', nullable: false)]
    private ?\DateTimeInterface $date_fin = null;

    public function getDate_fin(): ?\DateTimeInterface
    {
        return $this->date_fin;
    }

    public function setDate_fin(\DateTimeInterface $date_fin): self
    {
        $this->date_fin = $date_fin;
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

    #[ORM\ManyToOne(targetEntity: Lieu::class, inversedBy: 'offres')]
    #[ORM\JoinColumn(name: 'lieu_id', referencedColumnName: 'id')]
    private ?Lieu $lieu = null;

    public function getLieu(): ?Lieu
    {
        return $this->lieu;
    }

    public function setLieu(?Lieu $lieu): self
    {
        $this->lieu = $lieu;
        return $this;
    }

    #[ORM\OneToMany(targetEntity: CodePromo::class, mappedBy: 'offre')]
    private Collection $codePromos;

    /**
     * @return Collection<int, CodePromo>
     */
    public function getCodePromos(): Collection
    {
        if (!$this->codePromos instanceof Collection) {
            $this->codePromos = new ArrayCollection();
        }
        return $this->codePromos;
    }

    public function addCodePromo(CodePromo $codePromo): self
    {
        if (!$this->getCodePromos()->contains($codePromo)) {
            $this->getCodePromos()->add($codePromo);
        }
        return $this;
    }

    public function removeCodePromo(CodePromo $codePromo): self
    {
        $this->getCodePromos()->removeElement($codePromo);
        return $this;
    }

    #[ORM\OneToMany(targetEntity: Lieu::class, mappedBy: 'offre')]
    private Collection $lieus;

    /**
     * @return Collection<int, Lieu>
     */
    public function getLieus(): Collection
    {
        if (!$this->lieus instanceof Collection) {
            $this->lieus = new ArrayCollection();
        }
        return $this->lieus;
    }

    public function addLieu(Lieu $lieu): self
    {
        if (!$this->getLieus()->contains($lieu)) {
            $this->getLieus()->add($lieu);
        }
        return $this;
    }

    public function removeLieu(Lieu $lieu): self
    {
        $this->getLieus()->removeElement($lieu);
        return $this;
    }

    #[ORM\OneToMany(targetEntity: ReservationOffre::class, mappedBy: 'offre')]
    private Collection $reservationOffres;

    public function __construct()
    {
        $this->codePromos = new ArrayCollection();
        $this->lieus = new ArrayCollection();
        $this->reservationOffres = new ArrayCollection();
    }

    /**
     * @return Collection<int, ReservationOffre>
     */
    public function getReservationOffres(): Collection
    {
        if (!$this->reservationOffres instanceof Collection) {
            $this->reservationOffres = new ArrayCollection();
        }
        return $this->reservationOffres;
    }

    public function addReservationOffre(ReservationOffre $reservationOffre): self
    {
        if (!$this->getReservationOffres()->contains($reservationOffre)) {
            $this->getReservationOffres()->add($reservationOffre);
        }
        return $this;
    }

    public function removeReservationOffre(ReservationOffre $reservationOffre): self
    {
        $this->getReservationOffres()->removeElement($reservationOffre);
        return $this;
    }

    public function getDateDebut(): ?\DateTime
    {
        return $this->date_debut;
    }

    public function setDateDebut(\DateTime $date_debut): static
    {
        $this->date_debut = $date_debut;

        return $this;
    }

    public function getDateFin(): ?\DateTime
    {
        return $this->date_fin;
    }

    public function setDateFin(\DateTime $date_fin): static
    {
        $this->date_fin = $date_fin;

        return $this;
    }

}
