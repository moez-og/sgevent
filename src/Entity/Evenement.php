<?php

namespace App\Entity;

use App\Repository\EvenementRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: EvenementRepository::class)]
#[ORM\Table(name: 'evenement')]
class Evenement
{
    // ========== CONSTANTES DE STATUTS ==========
    public const STATUT_OUVERT = 'OUVERT';
    public const STATUT_FERME = 'FERME';
    public const STATUT_ANNULE = 'ANNULE';
    public const STATUTS_VALIDES = [self::STATUT_OUVERT, self::STATUT_FERME, self::STATUT_ANNULE];

    public const TYPE_PUBLIC = 'PUBLIC';
    public const TYPE_PRIVE = 'PRIVE';
    public const TYPES_VALIDES = [self::TYPE_PUBLIC, self::TYPE_PRIVE];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'date_creation', type: 'datetime')]
    private ?\DateTimeInterface $dateCreation = null;

    #[ORM\Column(length: 140)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 140)]
    private ?string $titre = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 2000)]
    private ?string $description = null;

    #[ORM\Column(name: 'date_debut', type: 'datetime')]
    #[Assert\NotBlank]
    private ?\DateTimeInterface $dateDebut = null;

    #[ORM\Column(name: 'date_fin', type: 'datetime')]
    #[Assert\NotBlank]
    #[Assert\GreaterThan(propertyPath: 'dateDebut')]
    private ?\DateTimeInterface $dateFin = null;

    #[ORM\Column(name: 'capacite_max', type: 'integer')]
    #[Assert\Positive]
    private ?int $capaciteMax = null;

    #[ORM\ManyToOne(targetEntity: Lieu::class, inversedBy: 'evenements')]  // on inverse correctement si Lieu a la collection
    #[ORM\JoinColumn(name: 'lieu_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Lieu $lieu = null;

    #[ORM\Column(length: 10)]
    #[Assert\Choice(choices: self::STATUTS_VALIDES, message: 'Statut invalide.')]
    private string $statut = 'OUVERT';

    #[ORM\Column(length: 10)]
    #[Assert\Choice(choices: self::TYPES_VALIDES, message: 'Type invalide.')]
    private string $type = 'PUBLIC';

    #[ORM\Column(name: 'image_url', length: 500, nullable: true)]
    private ?string $imageUrl = null;

    #[ORM\Column(type: 'float')]
    #[Assert\PositiveOrZero]
    private float $prix = 0.0;

    #[ORM\OneToMany(mappedBy: 'evenement', targetEntity: Inscription::class, cascade: ['persist', 'remove'])]
    private Collection $inscriptions;

    public function __construct()
    {
        $this->inscriptions = new ArrayCollection();
        $this->dateCreation = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getDateCreation(): ?\DateTimeInterface { return $this->dateCreation; }
    public function setDateCreation(\DateTimeInterface $dateCreation): self { $this->dateCreation = $dateCreation; return $this; }
    public function getTitre(): ?string { return $this->titre; }
    public function setTitre(string $titre): self { $this->titre = $titre; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }
    public function getDateDebut(): ?\DateTimeInterface { return $this->dateDebut; }
    public function setDateDebut(?\DateTimeInterface $dateDebut): self { $this->dateDebut = $dateDebut; return $this; }
    public function getDateFin(): ?\DateTimeInterface { return $this->dateFin; }
    public function setDateFin(?\DateTimeInterface $dateFin): self { $this->dateFin = $dateFin; return $this; }
    public function getCapaciteMax(): ?int { return $this->capaciteMax; }
    public function setCapaciteMax(int $capaciteMax): self { $this->capaciteMax = $capaciteMax; return $this; }
    public function getLieu(): ?Lieu { return $this->lieu; }
    public function setLieu(?Lieu $lieu): self { $this->lieu = $lieu; return $this; }
    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $statut): self { $this->statut = $statut; return $this; }
    public function getType(): string { return $this->type; }
    public function setType(string $type): self { $this->type = $type; return $this; }
    public function getImageUrl(): ?string { return $this->imageUrl; }
    public function setImageUrl(?string $imageUrl): self { $this->imageUrl = $imageUrl; return $this; }
    public function getPrix(): float { return $this->prix; }
    public function setPrix(float $prix): self { $this->prix = $prix; return $this; }
    public function getInscriptions(): Collection { return $this->inscriptions; }

    public function getPlacesRestantes(): int
    {
        $utilise = $this->inscriptions
            ->filter(fn(Inscription $i) => in_array($i->getStatut(), [Inscription::STATUT_CONFIRMEE, Inscription::STATUT_PAYEE], true))
            ->reduce(fn(int $carry, Inscription $i) => $carry + $i->getNbTickets(), 0);
        return max(0, ($this->capaciteMax ?? 0) - $utilise);
    }

    // ========== MÉTHODES MÉTIER ==========
    /**
     * Vérifie si l'événement a assez de places pour le nombre de tickets demandé
     */
    public function avoirPlacesPour(int $nbTickets): bool
    {
        return $this->getPlacesRestantes() >= $nbTickets;
    }

    /**
     * Vérifie si l'événement est encore ouvert aux inscriptions
     */
    public function estOuvert(): bool
    {
        return $this->statut === self::STATUT_OUVERT && new \DateTime() < $this->dateDebut;
    }

    /**
     * Obtient le nombre de confirmations en attente de paiement
     */
    public function getNbInscriptionsEnAttentePaiement(): int
    {
        return $this->inscriptions->filter(fn(Inscription $i) => 
            $i->getStatut() === 'CONFIRMEE' && !$i->isPaiementEffectue()
        )->count();
    }

    /**
     * Obtient le taux de remplissage (en %)
     */
    public function getTauxRemplissage(): float
    {
        if ($this->capaciteMax === 0) return 0;
        $utilise = $this->capaciteMax - $this->getPlacesRestantes();
        return round(($utilise / $this->capaciteMax) * 100, 2);
    }
}