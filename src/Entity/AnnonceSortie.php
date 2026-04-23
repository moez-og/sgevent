<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;

use App\Repository\AnnonceSortieRepository;

#[ORM\Entity(repositoryClass: AnnonceSortieRepository::class)]
#[ORM\Table(name: 'annonce_sortie')]
class AnnonceSortie
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

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'annonceSorties')]
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

    #[ORM\Column(type: 'string', length: 140, nullable: false)]
    #[Assert\NotBlank(message: 'Le titre est obligatoire.')]
    #[Assert\Length(max: 140, maxMessage: 'Le titre ne doit pas depasser {{ limit }} caracteres.')]
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
    #[Assert\Length(max: 5000, maxMessage: 'La description est trop longue.')]
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

    #[ORM\Column(type: 'string', length: 80, nullable: false)]
    #[Assert\NotBlank(message: 'La ville est obligatoire.')]
    #[Assert\Choice(
        choices: [
            'Tunis', 'Ariana', 'Ben Arous', 'Manouba', 'Nabeul', 'Zaghouan', 'Bizerte', 'Beja',
            'Jendouba', 'Kef', 'Siliana', 'Sousse', 'Monastir', 'Mahdia', 'Sfax', 'Kairouan',
            'Kasserine', 'Sidi Bouzid', 'Gabes', 'Medenine', 'Tataouine', 'Gafsa', 'Tozeur', 'Kebili'
        ],
        message: 'Veuillez choisir une ville tunisienne valide.'
    )]
    private ?string $ville = null;

    public function getVille(): ?string
    {
        return $this->ville;
    }

    public function setVille(string $ville): self
    {
        $this->ville = $ville;
        return $this;
    }

    #[ORM\Column(type: 'string', length: 255, nullable: false)]
    #[Assert\NotBlank(message: 'Le lieu est obligatoire.')]
    #[Assert\Length(max: 255, maxMessage: 'Le lieu ne doit pas depasser {{ limit }} caracteres.')]
    private ?string $lieu_texte = null;

    public function getLieu_texte(): ?string
    {
        return $this->lieu_texte;
    }

    public function setLieu_texte(string $lieu_texte): self
    {
        $this->lieu_texte = $lieu_texte;
        return $this;
    }

    #[ORM\Column(type: 'string', length: 255, nullable: false)]
    #[Assert\NotBlank(message: 'Le point de rencontre est obligatoire.')]
    #[Assert\Length(max: 255, maxMessage: 'Le point de rencontre est trop long.')]
    private ?string $point_rencontre = null;

    public function getPoint_rencontre(): ?string
    {
        return $this->point_rencontre;
    }

    public function setPoint_rencontre(string $point_rencontre): self
    {
        $this->point_rencontre = $point_rencontre;
        return $this;
    }

    #[ORM\Column(type: 'string', length: 80, nullable: false)]
    #[Assert\NotBlank(message: 'Le type d activite est obligatoire.')]
    #[Assert\Length(max: 80, maxMessage: 'Le type d activite ne doit pas depasser {{ limit }} caracteres.')]
    private ?string $type_activite = null;

    public function getType_activite(): ?string
    {
        return $this->type_activite;
    }

    public function setType_activite(string $type_activite): self
    {
        $this->type_activite = $type_activite;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: false)]
    #[Assert\NotNull(message: 'La date de sortie est obligatoire.')]
    #[Assert\GreaterThan('today', message: 'La date de sortie doit etre superieure a aujourd hui.')]
    private ?\DateTimeInterface $date_sortie = null;

    public function getDate_sortie(): ?\DateTimeInterface
    {
        return $this->date_sortie;
    }

    public function setDate_sortie(\DateTimeInterface $date_sortie): self
    {
        $this->date_sortie = $date_sortie;
        return $this;
    }

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: false)]
    #[Assert\NotNull(message: 'Le budget est obligatoire. Mettez 0 pour Gratuit.')]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Le budget doit etre positif ou nul (0 pour Gratuit).')]
    private ?float $budget_max = null;

    public function getBudget_max(): ?float
    {
        return $this->budget_max;
    }

    public function setBudget_max(float $budget_max): self
    {
        $this->budget_max = $budget_max;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: false)]
    #[Assert\NotNull(message: 'Le nombre de places est obligatoire.')]
    #[Assert\Positive(message: 'Le nombre de places doit etre strictement positif.')]
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

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    #[Assert\Length(max: 500, maxMessage: 'L URL de l image ne doit pas depasser {{ limit }} caracteres.')]
    private ?string $image_url = null;

    public function getImage_url(): ?string
    {
        return $this->image_url;
    }

    public function setImage_url(?string $image_url): self
    {
        $this->image_url = $image_url;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    #[Assert\NotBlank(message: 'Le statut est obligatoire.')]
    #[Assert\Choice(choices: ['OUVERTE', 'CLOTUREE', 'ANNULEE', 'TERMINEE'], message: 'Statut invalide.')]
    private ?string $statut = 'OUVERTE';

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): self
    {
        $this->statut = $statut;
        return $this;
    }

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Json(message: 'Les questions doivent etre au format JSON valide.')]
    private ?string $questions_json = null;

    public function getQuestions_json(): ?string
    {
        return $this->questions_json;
    }

    public function setQuestions_json(?string $questions_json): self
    {
        $this->questions_json = $questions_json;
        return $this;
    }

    #[ORM\OneToMany(targetEntity: ChatMessage::class, mappedBy: 'annonceSortie')]
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

    #[ORM\OneToOne(targetEntity: ParticipationAnnonce::class, mappedBy: 'annonceSortie')]
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

    #[ORM\OneToMany(targetEntity: Poll::class, mappedBy: 'annonceSortie')]
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

    #[ORM\OneToMany(targetEntity: SortieMedia::class, mappedBy: 'annonceSortie')]
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

    #[ORM\OneToOne(targetEntity: SortieRecap::class, mappedBy: 'annonceSortie')]
    private ?SortieRecap $sortieRecap = null;

    public function getSortieRecap(): ?SortieRecap
    {
        return $this->sortieRecap;
    }

    public function setSortieRecap(?SortieRecap $sortieRecap): self
    {
        $this->sortieRecap = $sortieRecap;
        return $this;
    }

    #[ORM\OneToMany(targetEntity: SortieTask::class, mappedBy: 'annonceSortie')]
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

    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'annonceSorties')]
    #[ORM\JoinTable(
        name: 'chat_read_state',
        joinColumns: [
            new ORM\JoinColumn(name: 'annonce_id', referencedColumnName: 'id')
        ],
        inverseJoinColumns: [
            new ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id')
        ]
    )]
    private Collection $users;

    public function __construct()
    {
        $this->chatMessages = new ArrayCollection();
        $this->polls = new ArrayCollection();
        $this->sortieMedias = new ArrayCollection();
        $this->sortieTasks = new ArrayCollection();
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

    public function getLieuTexte(): ?string
    {
        return $this->lieu_texte;
    }

    public function setLieuTexte(?string $lieu_texte): static
    {
        $this->lieu_texte = $lieu_texte;

        return $this;
    }

    public function getPointRencontre(): ?string
    {
        return $this->point_rencontre;
    }

    public function setPointRencontre(?string $point_rencontre): static
    {
        $this->point_rencontre = $point_rencontre;

        return $this;
    }

    public function getTypeActivite(): ?string
    {
        return $this->type_activite;
    }

    public function setTypeActivite(?string $type_activite): static
    {
        $this->type_activite = $type_activite;

        return $this;
    }

    public function getDateSortie(): ?\DateTimeInterface
    {
        return $this->date_sortie;
    }

    public function setDateSortie(?\DateTimeInterface $date_sortie): static
    {
        $this->date_sortie = $date_sortie;

        return $this;
    }

    public function getBudgetMax(): ?float
    {
        return $this->budget_max;
    }

    public function setBudgetMax(?float $budget_max): static
    {
        $this->budget_max = $budget_max;

        return $this;
    }

    public function getNbPlaces(): ?int
    {
        return $this->nb_places;
    }

    public function setNbPlaces(?int $nb_places): static
    {
        $this->nb_places = $nb_places;

        return $this;
    }

    public function getImageUrl(): ?string
    {
        return $this->image_url;
    }

    public function setImageUrl(?string $image_url): static
    {
        $this->image_url = $image_url;

        return $this;
    }

    public function getQuestionsJson(): ?string
    {
        return $this->questions_json;
    }

    public function setQuestionsJson(?string $questions_json): static
    {
        $this->questions_json = $questions_json;

        return $this;
    }

}
