<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\UserRepository;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'user')]
#[UniqueEntity(fields: ['email'], message: 'Cet email est deja utilise.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
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
    #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
    #[Assert\Length(min: 2, max: 50, minMessage: 'Le nom doit contenir au moins {{ limit }} caracteres.', maxMessage: 'Le nom ne peut pas depasser {{ limit }} caracteres.')]
    #[Assert\Regex(pattern: '/^[A-Za-zÀ-ÿ\s\-\']+$/u', message: 'Le nom contient des caracteres invalides.')]
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

    #[ORM\Column(type: 'string', nullable: false)]
    #[Assert\NotBlank(message: 'Le prenom est obligatoire.')]
    #[Assert\Length(min: 2, max: 50, minMessage: 'Le prenom doit contenir au moins {{ limit }} caracteres.', maxMessage: 'Le prenom ne peut pas depasser {{ limit }} caracteres.')]
    #[Assert\Regex(pattern: '/^[A-Za-zÀ-ÿ\s\-\']+$/u', message: 'Le prenom contient des caracteres invalides.')]
    private ?string $prenom = null;

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): self
    {
        $this->prenom = $prenom;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    #[Assert\NotBlank(message: 'L\'email est obligatoire.')]
    #[Assert\Email(message: 'Le format de l\'email est invalide.')]
    #[Assert\Length(max: 180, maxMessage: 'L\'email ne peut pas depasser {{ limit }} caracteres.')]
    private ?string $email = null;

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $password_hash = null;

    public function getPassword_hash(): ?string
    {
        return $this->password_hash;
    }

    public function setPassword_hash(string $password_hash): self
    {
        $this->password_hash = $password_hash;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    #[Assert\NotBlank(message: 'Le role est obligatoire.')]
    #[Assert\Choice(choices: ['admin', 'abonne', 'visiteur', 'ROLE_USER'], message: 'Le role {{ value }} est invalide.')]
    private ?string $role = null;

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function setRole(string $role): self
    {
        $this->role = $role;
        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getRoles(): array
    {
        $role = strtolower((string) $this->role);

        return match ($role) {
            'admin' => ['ROLE_ADMIN'],
            'abonne' => ['ROLE_ABONNE'],
            'visiteur' => ['ROLE_VISITEUR'],
            'role_admin' => ['ROLE_ADMIN'],
            'role_abonne' => ['ROLE_ABONNE'],
            'role_visiteur' => ['ROLE_VISITEUR'],
            default => ['ROLE_USER'],
        };
    }

    public function getPassword(): ?string
    {
        return $this->password_hash;
    }

    public function eraseCredentials(): void
    {
    }

    #[ORM\Column(type: 'string', nullable: true)]
    #[Assert\Length(max: 20, maxMessage: 'Le telephone ne peut pas depasser {{ limit }} caracteres.')]
    #[Assert\Regex(pattern: '/^(\+?\d[\d\s\-]{7,15})$/', message: 'Numero de telephone invalide.')]
    private ?string $telephone = null;

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): self
    {
        $this->telephone = $telephone;
        return $this;
    }

    #[ORM\Column(name: 'imageUrl', type: 'string', nullable: false)]
    #[Assert\NotBlank(message: 'L\'image utilisateur est obligatoire.')]
    #[Assert\Length(max: 255, maxMessage: 'Le chemin de l\'image ne peut pas depasser {{ limit }} caracteres.')]
    private ?string $imageUrl = null;

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function setImageUrl(string $imageUrl): self
    {
        $this->imageUrl = $imageUrl;
        return $this;
    }

    #[ORM\OneToMany(targetEntity: AnnonceSortie::class, mappedBy: 'user')]
    private Collection $annonceSorties;

    /**
     * @return Collection<int, AnnonceSortie>
     */
    public function getAnnonceSorties(): Collection
    {
        if (!$this->annonceSorties instanceof Collection) {
            $this->annonceSorties = new ArrayCollection();
        }
        return $this->annonceSorties;
    }

    public function addAnnonceSortie(AnnonceSortie $annonceSortie): self
    {
        if (!$this->getAnnonceSorties()->contains($annonceSortie)) {
            $this->getAnnonceSorties()->add($annonceSortie);
        }
        return $this;
    }

    public function removeAnnonceSortie(AnnonceSortie $annonceSortie): self
    {
        $this->getAnnonceSorties()->removeElement($annonceSortie);
        return $this;
    }

    #[ORM\OneToMany(targetEntity: ChatMessage::class, mappedBy: 'user')]
    private Collection $chatMessages;

    /**
     * @return Collection<int, ChatMessage>
     */
    public function getChatMessages(): Collection
    {
        if (!$this->chatMessages instanceof Collection) {
            $this->chatMessages = new ArrayCollection();
        }
        return $this->chatMessages;
    }

    public function addChatMessage(ChatMessage $chatMessage): self
    {
        if (!$this->getChatMessages()->contains($chatMessage)) {
            $this->getChatMessages()->add($chatMessage);
        }
        return $this;
    }

    public function removeChatMessage(ChatMessage $chatMessage): self
    {
        $this->getChatMessages()->removeElement($chatMessage);
        return $this;
    }

    #[ORM\OneToMany(targetEntity: CodePromo::class, mappedBy: 'user')]
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

    #[ORM\OneToOne(targetEntity: EvaluationLieu::class, mappedBy: 'user')]
    private ?EvaluationLieu $evaluationLieu = null;

    public function getEvaluationLieu(): ?EvaluationLieu
    {
        return $this->evaluationLieu;
    }

    public function setEvaluationLieu(?EvaluationLieu $evaluationLieu): self
    {
        $this->evaluationLieu = $evaluationLieu;
        return $this;
    }

    #[ORM\OneToOne(targetEntity: Inscription::class, mappedBy: 'user')]
    private ?Inscription $inscription = null;

    public function getInscription(): ?Inscription
    {
        return $this->inscription;
    }

    public function setInscription(?Inscription $inscription): self
    {
        $this->inscription = $inscription;
        return $this;
    }

    #[ORM\OneToMany(targetEntity: Notification::class, mappedBy: 'user')]
    private Collection $notifications;

    /**
     * @return Collection<int, Notification>
     */
    public function getNotifications(): Collection
    {
        if (!$this->notifications instanceof Collection) {
            $this->notifications = new ArrayCollection();
        }
        return $this->notifications;
    }

    public function addNotification(Notification $notification): self
    {
        if (!$this->getNotifications()->contains($notification)) {
            $this->getNotifications()->add($notification);
        }
        return $this;
    }

    public function removeNotification(Notification $notification): self
    {
        $this->getNotifications()->removeElement($notification);
        return $this;
    }

    #[ORM\OneToOne(targetEntity: Notification::class, mappedBy: 'user')]
    private ?Notification $notification = null;

    public function getNotification(): ?Notification
    {
        return $this->notification;
    }

    public function setNotification(?Notification $notification): self
    {
        $this->notification = $notification;
        return $this;
    }

    #[ORM\OneToMany(targetEntity: Offre::class, mappedBy: 'user')]
    private Collection $offres;

    /**
     * @return Collection<int, Offre>
     */
    public function getOffres(): Collection
    {
        if (!$this->offres instanceof Collection) {
            $this->offres = new ArrayCollection();
        }
        return $this->offres;
    }

    public function addOffre(Offre $offre): self
    {
        if (!$this->getOffres()->contains($offre)) {
            $this->getOffres()->add($offre);
        }
        return $this;
    }

    public function removeOffre(Offre $offre): self
    {
        $this->getOffres()->removeElement($offre);
        return $this;
    }

    #[ORM\OneToOne(targetEntity: OffreBadgeUser::class, mappedBy: 'user')]
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

    #[ORM\OneToOne(targetEntity: ParticipationAnnonce::class, mappedBy: 'user')]
    private ?ParticipationAnnonce $participationAnnonce = null;

    public function getParticipationAnnonce(): ?ParticipationAnnonce
    {
        return $this->participationAnnonce;
    }

    public function setParticipationAnnonce(?ParticipationAnnonce $participationAnnonce): self
    {
        $this->participationAnnonce = $participationAnnonce;
        return $this;
    }

    #[ORM\OneToMany(targetEntity: Poll::class, mappedBy: 'user')]
    private Collection $polls;

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

    #[ORM\OneToMany(targetEntity: PollOption::class, mappedBy: 'user')]
    private Collection $pollOptions;

    /**
     * @return Collection<int, PollOption>
     */
    public function getPollOptions(): Collection
    {
        if (!$this->pollOptions instanceof Collection) {
            $this->pollOptions = new ArrayCollection();
        }
        return $this->pollOptions;
    }

    public function addPollOption(PollOption $pollOption): self
    {
        if (!$this->getPollOptions()->contains($pollOption)) {
            $this->getPollOptions()->add($pollOption);
        }
        return $this;
    }

    public function removePollOption(PollOption $pollOption): self
    {
        $this->getPollOptions()->removeElement($pollOption);
        return $this;
    }

    #[ORM\OneToMany(targetEntity: ReservationOffre::class, mappedBy: 'user')]
    private Collection $reservationOffres;

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

    #[ORM\OneToMany(targetEntity: SortieMedia::class, mappedBy: 'user')]
    private Collection $sortieMedias;

    /**
     * @return Collection<int, SortieMedia>
     */
    public function getSortieMedias(): Collection
    {
        if (!$this->sortieMedias instanceof Collection) {
            $this->sortieMedias = new ArrayCollection();
        }
        return $this->sortieMedias;
    }

    public function addSortieMedia(SortieMedia $sortieMedia): self
    {
        if (!$this->getSortieMedias()->contains($sortieMedia)) {
            $this->getSortieMedias()->add($sortieMedia);
        }
        return $this;
    }

    public function removeSortieMedia(SortieMedia $sortieMedia): self
    {
        $this->getSortieMedias()->removeElement($sortieMedia);
        return $this;
    }

    #[ORM\OneToMany(targetEntity: SortieTask::class, mappedBy: 'user')]
    private Collection $sortieTasks;

    /**
     * @return Collection<int, SortieTask>
     */
    public function getSortieTasks(): Collection
    {
        if (!$this->sortieTasks instanceof Collection) {
            $this->sortieTasks = new ArrayCollection();
        }
        return $this->sortieTasks;
    }

    public function addSortieTask(SortieTask $sortieTask): self
    {
        if (!$this->getSortieTasks()->contains($sortieTask)) {
            $this->getSortieTasks()->add($sortieTask);
        }
        return $this;
    }

    public function removeSortieTask(SortieTask $sortieTask): self
    {
        $this->getSortieTasks()->removeElement($sortieTask);
        return $this;
    }

    #[ORM\OneToMany(targetEntity: UserPoint::class, mappedBy: 'user')]
    private Collection $userPoints;

    /**
     * @return Collection<int, UserPoint>
     */
    public function getUserPoints(): Collection
    {
        if (!$this->userPoints instanceof Collection) {
            $this->userPoints = new ArrayCollection();
        }
        return $this->userPoints;
    }

    public function addUserPoint(UserPoint $userPoint): self
    {
        if (!$this->getUserPoints()->contains($userPoint)) {
            $this->getUserPoints()->add($userPoint);
        }
        return $this;
    }

    public function removeUserPoint(UserPoint $userPoint): self
    {
        $this->getUserPoints()->removeElement($userPoint);
        return $this;
    }

    #[ORM\ManyToMany(targetEntity: AnnonceSortie::class, inversedBy: 'users')]
    #[ORM\JoinTable(
        name: 'chat_read_state',
        joinColumns: [
            new ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id')
        ],
        inverseJoinColumns: [
            new ORM\JoinColumn(name: 'annonce_id', referencedColumnName: 'id')
        ]
    )]
    private Collection $readChats;

    /**
     * @return Collection<int, AnnonceSortie>
     */
    public function getReadChats(): Collection
    {
        if (!$this->readChats instanceof Collection) {
            $this->readChats = new ArrayCollection();
        }
        return $this->readChats;
    }

    public function addReadChat(AnnonceSortie $annonceSortie): self
    {
        if (!$this->getReadChats()->contains($annonceSortie)) {
            $this->getReadChats()->add($annonceSortie);
        }
        return $this;
    }

    public function removeReadChat(AnnonceSortie $annonceSortie): self
    {
        $this->getReadChats()->removeElement($annonceSortie);
        return $this;
    }

    #[ORM\ManyToMany(targetEntity: Lieu::class, inversedBy: 'users')]
    #[ORM\JoinTable(
        name: 'favori_lieu',
        joinColumns: [
            new ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id')
        ],
        inverseJoinColumns: [
            new ORM\JoinColumn(name: 'lieu_id', referencedColumnName: 'id')
        ]
    )]
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

    #[ORM\ManyToMany(targetEntity: Badge::class, inversedBy: 'users')]
    #[ORM\JoinTable(
        name: 'user_badge',
        joinColumns: [
            new ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id')
        ],
        inverseJoinColumns: [
            new ORM\JoinColumn(name: 'badge_id', referencedColumnName: 'id')
        ]
    )]
    private Collection $badges;

    public function __construct()
    {
        $this->annonceSorties = new ArrayCollection();
        $this->chatMessages = new ArrayCollection();
        $this->codePromos = new ArrayCollection();
        $this->notifications = new ArrayCollection();
        $this->offres = new ArrayCollection();
        $this->polls = new ArrayCollection();
        $this->pollOptions = new ArrayCollection();
        $this->reservationOffres = new ArrayCollection();
        $this->sortieMedias = new ArrayCollection();
        $this->sortieTasks = new ArrayCollection();
        $this->userPoints = new ArrayCollection();
        $this->readChats = new ArrayCollection();
        $this->lieus = new ArrayCollection();
        $this->badges = new ArrayCollection();
    }

    /**
     * @return Collection<int, Badge>
     */
    public function getBadges(): Collection
    {
        if (!$this->badges instanceof Collection) {
            $this->badges = new ArrayCollection();
        }
        return $this->badges;
    }

    public function addBadge(Badge $badge): self
    {
        if (!$this->getBadges()->contains($badge)) {
            $this->getBadges()->add($badge);
        }
        return $this;
    }

    public function removeBadge(Badge $badge): self
    {
        $this->getBadges()->removeElement($badge);
        return $this;
    }

    public function getPasswordHash(): ?string
    {
        return $this->password_hash;
    }

    public function setPasswordHash(string $password_hash): static
    {
        $this->password_hash = $password_hash;

        return $this;
    }

    public function addAnnonceSorty(AnnonceSortie $annonceSorty): static
    {
        if (!$this->annonceSorties->contains($annonceSorty)) {
            $this->annonceSorties->add($annonceSorty);
            $annonceSorty->setUser($this);
        }

        return $this;
    }

    public function removeAnnonceSorty(AnnonceSortie $annonceSorty): static
    {
        if ($this->annonceSorties->removeElement($annonceSorty)) {
            // set the owning side to null (unless already changed)
            if ($annonceSorty->getUser() === $this) {
                $annonceSorty->setUser(null);
            }
        }

        return $this;
    }

}

