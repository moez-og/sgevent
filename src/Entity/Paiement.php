<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'paiement')]
class Paiement
{
    // ========== CONSTANTES DE STATUTS ==========
    public const STATUT_EN_ATTENTE = 'EN_ATTENTE';      // Paiement pas encore efectué
    public const STATUT_PAYE = 'PAYE';                  // Paiement réussi
    public const STATUT_ECHOUE = 'ECHOUE';              // Paiement échoué
    public const STATUT_REMBOURSE = 'REMBOURSE';        // Paiement remboursé
    public const STATUTS_VALIDES = [
        self::STATUT_EN_ATTENTE,
        self::STATUT_PAYE,
        self::STATUT_ECHOUE,
        self::STATUT_REMBOURSE,
    ];

    public const METHODE_CARTE = 'CARTE';
    public const METHODE_CASH = 'CASH';
    public const METHODE_WALLET = 'WALLET';
    public const METHODES_VALIDES = [
        self::METHODE_CARTE,
        self::METHODE_CASH,
        self::METHODE_WALLET,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Inscription::class, inversedBy: 'paiements')]
    #[ORM\JoinColumn(name: 'inscription_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Inscription $inscription = null;

    #[ORM\Column(type: 'float')]
    #[Assert\Positive]
    private float $montant = 0.0;

    #[ORM\Column(type: 'string', length: 50)]
    private string $methode = '';

    #[ORM\Column(type: 'string', length: 30)]
    private string $statut = 'PAYE';

    #[ORM\Column(name: 'reference_code', type: 'string', length: 100)]
    private string $referenceCode = '';

    #[ORM\Column(name: 'nom_carte', type: 'string', length: 100, nullable: true)]
    private ?string $nomCarte = null;

    #[ORM\Column(name: 'quatre_derniers', type: 'string', length: 4, nullable: true)]
    private ?string $quatreDerniers = null;

    #[ORM\Column(name: 'date_paiement', type: 'datetime')]
    private ?\DateTimeInterface $datePaiement = null;

    public function __construct()
    {
        $this->datePaiement = new \DateTime();
    }

    // GETTERS / SETTERS (identiques à la logique Java)
    public function getId(): ?int { return $this->id; }
    public function getInscription(): ?Inscription { return $this->inscription; }
    public function setInscription(Inscription $inscription): self { $this->inscription = $inscription; return $this; }
    public function getMontant(): float { return $this->montant; }
    public function setMontant(float $montant): self { $this->montant = $montant; return $this; }
    public function getMethode(): string { return $this->methode; }
    public function setMethode(string $methode): self { $this->methode = $methode; return $this; }
    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $statut): self { $this->statut = $statut; return $this; }
    public function getReferenceCode(): string { return $this->referenceCode; }
    public function setReferenceCode(string $referenceCode): self { $this->referenceCode = $referenceCode; return $this; }
    public function getNomCarte(): ?string { return $this->nomCarte; }
    public function setNomCarte(?string $nomCarte): self { $this->nomCarte = $nomCarte; return $this; }
    public function getQuatreDerniers(): ?string { return $this->quatreDerniers; }
    public function setQuatreDerniers(?string $quatreDerniers): self { $this->quatreDerniers = $quatreDerniers; return $this; }
    public function getDatePaiement(): ?\DateTimeInterface { return $this->datePaiement; }
    public function setDatePaiement(\DateTimeInterface $datePaiement): self { $this->datePaiement = $datePaiement; return $this; }

    // ========== MÉTHODES MÉTIER ==========
    /**
     * Vérifie si le paiement a réussi
     */
    public function estReussi(): bool
    {
        return $this->statut === self::STATUT_PAYE;
    }

    /**
     * Vérifie si le paiement peut être réessayé
     */
    public function peutEtreReessaye(): bool
    {
        return $this->statut === self::STATUT_ECHOUE;
    }

    /**
     * Obtient l'affichage du statut en français
     */
    public function getStatutLabel(): string
    {
        return match($this->statut) {
            self::STATUT_EN_ATTENTE => 'En attente',
            self::STATUT_PAYE => 'Payé',
            self::STATUT_ECHOUE => 'Échoué',
            self::STATUT_REMBOURSE => 'Remboursé',
            default => $this->statut,
        };
    }
}