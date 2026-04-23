<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'ticket')]
class Ticket
{
    // ========== CONSTANTES DE STATUTS ==========
    public const STATUT_VALIDE = 'VALIDE';
    public const STATUT_UTILISE = 'UTILISE';
    public const STATUT_ANNULE = 'ANNULE';
    public const STATUTS_VALIDES = [self::STATUT_VALIDE, self::STATUT_UTILISE, self::STATUT_ANNULE];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Inscription::class, inversedBy: 'tickets')]
    #[ORM\JoinColumn(name: 'inscription_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Inscription $inscription = null;

    #[ORM\Column(type: 'date')]
    private ?\DateTimeInterface $date = null;

    // ========== PROPRIÉTÉS TRANSIENTES (non-persistées) ==========
    private ?string $numeroTicket = null;
    private string $statut = self::STATUT_VALIDE;
    private ?string $codeValidation = null;

    public function __construct()
    {
        $this->date = new \DateTime();
        $this->codeValidation = bin2hex(random_bytes(8)); // Code unique 16 caractères
        $this->numeroTicket = $this->genererNumeroTicket();
    }

    public function getId(): ?int { return $this->id; }
    public function getInscription(): ?Inscription { return $this->inscription; }
    public function setInscription(Inscription $inscription): self { $this->inscription = $inscription; return $this; }
    public function getDate(): ?\DateTimeInterface { return $this->date; }
    public function setDate(\DateTimeInterface $date): self { $this->date = $date; return $this; }
    public function getNumeroTicket(): ?string { return $this->numeroTicket; }
    public function getCodeValidation(): ?string { return $this->codeValidation; }
    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $statut): self { $this->statut = $statut; return $this; }

    // ========== MÉTHODES MÉTIER ==========
    /**
     * Génère un numéro de ticket unique
     * Format: TICKET-EVENT{eventId}-{inscriptionId}-{ticketId}
     */
    private function genererNumeroTicket(): string
    {
        $eventId = $this->inscription?->getEvenement()?->getId() ?? 'UNKN';
        $inscriptionId = $this->inscription?->getId() ?? 'UNKN';
        $timestamp = date('Ymd');
        
        return "TICKET-{$eventId}-{$inscriptionId}-{$timestamp}-{$this->id}";
    }

    /**
     * Obtient la version finale du numéro si l'ID est généré
     */
    public function getNumeroTicketFinal(): string
    {
        return $this->genererNumeroTicket();
    }

    /**
     * Marque le ticket comme utilisé
     */
    public function marquerCommeUtilise(): self
    {
        if ($this->statut === self::STATUT_VALIDE) {
            $this->statut = self::STATUT_UTILISE;
        }
        return $this;
    }

    /**
     * Vérifie si le ticket est valide
     */
    public function estValide(): bool
    {
        return $this->statut === self::STATUT_VALIDE;
    }

    /**
     * Obtient l'affichage du statut en français
     */
    public function getStatutLabel(): string
    {
        return match($this->statut) {
            self::STATUT_VALIDE => 'Valide',
            self::STATUT_UTILISE => 'Utilisé',
            self::STATUT_ANNULE => 'Annulé',
            default => $this->statut,
        };
    }
}