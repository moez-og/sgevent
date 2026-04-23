<?php

namespace App\Entity;

use App\Repository\InscriptionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: InscriptionRepository::class)]
#[ORM\Table(name: 'inscription')]
class Inscription
{
    // ========== CONSTANTES DE STATUTS ==========
    public const STATUT_EN_ATTENTE = 'EN_ATTENTE';          // Attente validation admin
    public const STATUT_CONFIRMEE = 'CONFIRMEE';            // Admin a accepté, en attente paiement
    public const STATUT_PAYEE = 'PAYEE';                    // Paiement effectué
    public const STATUT_ANNULEE = 'ANNULEE';                // Utilisateur/admin a annulé
    public const STATUT_REJETEE = 'REJETEE';                // Admin a refusé
    public const STATUTS_VALIDES = [
        self::STATUT_EN_ATTENTE,
        self::STATUT_CONFIRMEE,
        self::STATUT_PAYEE,
        self::STATUT_ANNULEE,
        self::STATUT_REJETEE,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

#[ORM\ManyToOne(targetEntity: User::class)]
#[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
private ?User $user = null;

#[ORM\ManyToOne(targetEntity: Evenement::class, inversedBy: 'inscriptions')]
#[ORM\JoinColumn(name: 'event_id', referencedColumnName: 'id', nullable: false)]
private ?Evenement $evenement = null;

    #[ORM\Column(name: 'date_creation', type: 'datetime')]
    private ?\DateTimeInterface $dateCreation = null;

    #[ORM\Column(type: 'string', length: 20)]
    #[Assert\Choice(choices: self::STATUTS_VALIDES)]
    private string $statut = 'EN_ATTENTE';

    #[ORM\Column(type: 'float')]
    private float $paiement = 0.0; // champ legacy du schéma

    #[ORM\Column(name: 'nb_tickets', type: 'integer')]
    #[Assert\Positive]
    private int $nbTickets = 1;

    #[ORM\OneToMany(mappedBy: 'inscription', targetEntity: Ticket::class, cascade: ['persist', 'remove'])]
    private Collection $tickets;

    #[ORM\OneToMany(mappedBy: 'inscription', targetEntity: Paiement::class)]
    private Collection $paiements;

    public function __construct()
    {
        $this->dateCreation = new \DateTime();
        $this->tickets = new ArrayCollection();
        $this->paiements = new ArrayCollection();
    }

    // GETTERS / SETTERS (je t’ai mis les principaux)
    public function getId(): ?int { return $this->id; }
    public function getEvenement(): ?Evenement { return $this->evenement; }
    public function setEvenement(Evenement $evenement): self { $this->evenement = $evenement; return $this; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(User $user): self { $this->user = $user; return $this; }
    public function getDateCreation(): ?\DateTimeInterface { return $this->dateCreation; }
    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $statut): self { $this->statut = $statut; return $this; }
    public function getPaiement(): float { return $this->paiement; }
    public function setPaiement(float $paiement): self { $this->paiement = $paiement; return $this; }
    public function getNbTickets(): int { return $this->nbTickets; }
    public function setNbTickets(int $nbTickets): self { $this->nbTickets = $nbTickets; return $this; }
    public function getTickets(): Collection { return $this->tickets; }
    public function getPaiements(): Collection { return $this->paiements; }

    // ========== MÉTHODES MÉTIER ==========
    /**
     * Vérifie si le paiement a été effectué
     */
    public function isPaiementEffectue(): bool
    {
        // Vérifier s'il y a au moins un paiement avec statut PAYE
        return $this->paiements->exists(fn(int $k, Paiement $p) => $p->getStatut() === 'PAYE');
    }

    /**
     * Obtient le paiement principal (le plus récent)
     */
    public function getPaiementPrincipal(): ?Paiement
    {
        if ($this->paiements->isEmpty()) {
            return null;
        }
        
        // Récupérer le dernier paiement
        return $this->paiements->getValues()[count($this->paiements) - 1] ?? null;
    }

    /**
     * Calcule le montant total à payer
     */
    public function getMontantTotal(): float
    {
        return ($this->evenement?->getPrix() ?? 0) * $this->nbTickets;
    }

    /**
     * Vérifie si l'inscription peut être confirmée
     */
    public function peutEtreConfirmee(): bool
    {
        return $this->statut === self::STATUT_EN_ATTENTE;
    }

    /**
     * Vérifie si l'inscription en attente de paiement
     */
    public function estEnAttentePaiement(): bool
    {
        return $this->statut === self::STATUT_CONFIRMEE && !$this->isPaiementEffectue();
    }

    /**
     * Vérifie si l'inscription est active (confirmée ou payée)
     */
    public function estActive(): bool
    {
        return in_array($this->statut, [self::STATUT_CONFIRMEE, self::STATUT_PAYEE]);
    }
}