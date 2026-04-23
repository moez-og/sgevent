<?php

namespace App\Entity;

use App\Enum\LieuCategorie;
use App\Enum\LieuType;
use App\Repository\LieuRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: LieuRepository::class)]
#[ORM\Table(name: 'lieu')]
class Lieu
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Offre::class, inversedBy: 'lieus')]
    #[ORM\JoinColumn(name: 'id_offre', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Offre $offre = null;

    #[ORM\Column(type: Types::STRING, length: 120, nullable: false)]
    #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
    #[Assert\Length(min: 2, minMessage: 'Le nom doit contenir au moins {{ limit }} caracteres.')]
    #[Assert\Length(max: 120, maxMessage: 'Le nom ne peut pas depasser {{ limit }} caracteres.')]
    private ?string $nom = null;

    #[ORM\Column(type: Types::STRING, length: 80, nullable: false)]
    #[Assert\NotBlank(message: 'La ville est obligatoire.')]
    #[Assert\Length(min: 2, minMessage: 'La ville doit contenir au moins {{ limit }} caracteres.')]
    #[Assert\Length(max: 80, maxMessage: 'La ville ne peut pas depasser {{ limit }} caracteres.')]
    private ?string $ville = null;

    #[ORM\Column(type: Types::STRING, length: 200, nullable: true)]
    #[Assert\Length(max: 200, maxMessage: 'L\'adresse ne peut pas depasser {{ limit }} caracteres.')]
    private ?string $adresse = null;

    #[ORM\Column(type: Types::STRING, length: 30, nullable: true)]
    #[Assert\Length(max: 30, maxMessage: 'Le telephone ne peut pas depasser {{ limit }} caracteres.')]
    #[Assert\Regex(pattern: '/^[0-9+\s().-]{6,30}$/', message: 'Le téléphone doit être valide.')]
    private ?string $telephone = null;

    #[ORM\Column(name: 'site_web', type: Types::STRING, length: 255, nullable: true)]
    #[Assert\Length(max: 255, maxMessage: 'Le site web ne peut pas depasser {{ limit }} caracteres.')]
    #[Assert\Url(message: 'Le site web doit être une URL valide.', protocols: ['http', 'https'])]
    private ?string $site_web = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Assert\Length(max: 255, maxMessage: 'Instagram ne peut pas depasser {{ limit }} caracteres.')]
    private ?string $instagram = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 5000, maxMessage: 'La description ne peut pas depasser {{ limit }} caracteres.')]
    private ?string $description = null;

    #[ORM\Column(name: 'budget_min', type: Types::FLOAT, nullable: true)]
    #[Assert\PositiveOrZero(message: 'Le budget minimum doit etre positif.')]
    private ?float $budget_min = null;

    #[ORM\Column(name: 'budget_max', type: Types::FLOAT, nullable: true)]
    #[Assert\PositiveOrZero(message: 'Le budget maximum doit etre positif.')]
    private ?float $budget_max = null;

    #[ORM\Column(enumType: LieuCategorie::class, length: 50, nullable: false)]
    #[Assert\NotNull(message: 'La catégorie est obligatoire.')]
    private ?LieuCategorie $categorie = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    #[Assert\Range(min: -90, max: 90, notInRangeMessage: 'La latitude doit etre comprise entre {{ min }} et {{ max }}.')]
    private ?float $latitude = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    #[Assert\Range(min: -180, max: 180, notInRangeMessage: 'La longitude doit etre comprise entre {{ min }} et {{ max }}.')]
    private ?float $longitude = null;

    #[ORM\Column(enumType: LieuType::class, length: 20, nullable: false)]
    #[Assert\NotNull(message: 'Le type est obligatoire.')]
    private ?LieuType $type = null;

    #[ORM\Column(name: 'image_url', type: Types::STRING, length: 500, nullable: true)]
    #[Assert\Length(max: 500, maxMessage: 'Le chemin de l\'image ne peut pas depasser {{ limit }} caracteres.')]
    private ?string $image_url = null;

    #[ORM\OneToOne(targetEntity: EvaluationLieu::class, mappedBy: 'lieu')]
    private ?EvaluationLieu $evaluationLieu = null;

    #[ORM\OneToMany(targetEntity: Evenement::class, mappedBy: 'lieu')]
    private Collection $evenements;

    #[ORM\OneToOne(targetEntity: LieuHoraire::class, mappedBy: 'lieu')]
    private ?LieuHoraire $lieuHoraire = null;

    #[ORM\OneToMany(targetEntity: LieuImage::class, mappedBy: 'lieu')]
    private Collection $lieuImages;

    #[ORM\OneToMany(targetEntity: Offre::class, mappedBy: 'lieu')]
    private Collection $offres;

    #[ORM\OneToMany(targetEntity: ReservationOffre::class, mappedBy: 'lieu')]
    private Collection $reservationOffres;

    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'lieus')]
    #[ORM\JoinTable(
        name: 'favori_lieu',
        joinColumns: [new ORM\JoinColumn(name: 'lieu_id', referencedColumnName: 'id')],
        inverseJoinColumns: [new ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id')]
    )]
    private Collection $users;

    public function __construct()
    {
        $this->evenements = new ArrayCollection();
        $this->lieuImages = new ArrayCollection();
        $this->offres = new ArrayCollection();
        $this->reservationOffres = new ArrayCollection();
        $this->users = new ArrayCollection();
    }

    public function __toString(): string
    {
        return (string) ($this->nom ?? 'Lieu');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getOffre(): ?Offre
    {
        return $this->offre;
    }

    public function setOffre(?Offre $offre): self
    {
        $this->offre = $offre;

        return $this;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): self
    {
        $this->nom = $nom;

        return $this;
    }

    public function getVille(): ?string
    {
        return $this->ville;
    }

    public function setVille(string $ville): self
    {
        $this->ville = $ville;

        return $this;
    }

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(?string $adresse): self
    {
        $this->adresse = $adresse;

        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): self
    {
        $this->telephone = $telephone;

        return $this;
    }

    public function getSite_web(): ?string
    {
        return $this->site_web;
    }

    public function setSite_web(?string $site_web): self
    {
        $this->site_web = $site_web;

        return $this;
    }

    public function getSiteWeb(): ?string
    {
        return $this->site_web;
    }

    public function setSiteWeb(?string $site_web): self
    {
        $this->site_web = $site_web;

        return $this;
    }

    public function getInstagram(): ?string
    {
        return $this->instagram;
    }

    public function setInstagram(?string $instagram): self
    {
        $this->instagram = $instagram;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getBudget_min(): ?float
    {
        return $this->budget_min;
    }

    public function setBudget_min(?float $budget_min): self
    {
        $this->budget_min = $budget_min;

        return $this;
    }

    public function getBudgetMin(): ?float
    {
        return $this->budget_min;
    }

    public function setBudgetMin(?float $budget_min): self
    {
        $this->budget_min = $budget_min;

        return $this;
    }

    public function getBudget_max(): ?float
    {
        return $this->budget_max;
    }

    public function setBudget_max(?float $budget_max): self
    {
        $this->budget_max = $budget_max;

        return $this;
    }

    public function getBudgetMax(): ?float
    {
        return $this->budget_max;
    }

    public function setBudgetMax(?float $budget_max): self
    {
        $this->budget_max = $budget_max;

        return $this;
    }

    public function getCategorie(): ?LieuCategorie
    {
        return $this->categorie;
    }

    public function setCategorie(?LieuCategorie $categorie): self
    {
        $this->categorie = $categorie;

        return $this;
    }

    public function getCategorieLabel(): ?string
    {
        return $this->categorie?->label();
    }

    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    public function setLatitude(?float $latitude): self
    {
        $this->latitude = $latitude;

        return $this;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    public function setLongitude(?float $longitude): self
    {
        $this->longitude = $longitude;

        return $this;
    }

    public function getType(): ?LieuType
    {
        return $this->type;
    }

    public function setType(?LieuType $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getTypeLabel(): ?string
    {
        return $this->type?->label();
    }

    public function getImage_url(): ?string
    {
        return $this->image_url;
    }

    public function setImage_url(?string $image_url): self
    {
        $this->image_url = $image_url;

        return $this;
    }

    public function getImageUrl(): ?string
    {
        return $this->image_url;
    }

    public function setImageUrl(?string $image_url): self
    {
        $this->image_url = $image_url;

        return $this;
    }

    #[Assert\Callback]
    public function validateBudgetRange(ExecutionContextInterface $context): void
    {
        if ($this->budget_min !== null && $this->budget_max !== null && $this->budget_min > $this->budget_max) {
            $context->buildViolation('Le budget minimum doit être inférieur ou égal au budget maximum.')
                ->atPath('budgetMax')
                ->addViolation();
        }
    }

    public function getEvaluationLieu(): ?EvaluationLieu
    {
        return $this->evaluationLieu;
    }

    public function setEvaluationLieu(?EvaluationLieu $evaluationLieu): self
    {
        $this->evaluationLieu = $evaluationLieu;

        return $this;
    }

    /**
     * @return Collection<int, Evenement>
     */
    public function getEvenements(): Collection
    {
        return $this->evenements;
    }

    public function addEvenement(Evenement $evenement): self
    {
        if (!$this->evenements->contains($evenement)) {
            $this->evenements->add($evenement);
        }

        return $this;
    }

    public function removeEvenement(Evenement $evenement): self
    {
        $this->evenements->removeElement($evenement);

        return $this;
    }

    public function getLieuHoraire(): ?LieuHoraire
    {
        return $this->lieuHoraire;
    }

    public function setLieuHoraire(?LieuHoraire $lieuHoraire): self
    {
        $this->lieuHoraire = $lieuHoraire;

        return $this;
    }

    /**
     * @return Collection<int, LieuImage>
     */
    public function getLieuImages(): Collection
    {
        return $this->lieuImages;
    }

    public function addLieuImage(LieuImage $lieuImage): self
    {
        if (!$this->lieuImages->contains($lieuImage)) {
            $this->lieuImages->add($lieuImage);
        }

        return $this;
    }

    public function removeLieuImage(LieuImage $lieuImage): self
    {
        $this->lieuImages->removeElement($lieuImage);

        return $this;
    }

    /**
     * @return Collection<int, Offre>
     */
    public function getOffres(): Collection
    {
        return $this->offres;
    }

    public function addOffre(Offre $offre): self
    {
        if (!$this->offres->contains($offre)) {
            $this->offres->add($offre);
        }

        return $this;
    }

    public function removeOffre(Offre $offre): self
    {
        $this->offres->removeElement($offre);

        return $this;
    }

    /**
     * @return Collection<int, ReservationOffre>
     */
    public function getReservationOffres(): Collection
    {
        return $this->reservationOffres;
    }

    public function addReservationOffre(ReservationOffre $reservationOffre): self
    {
        if (!$this->reservationOffres->contains($reservationOffre)) {
            $this->reservationOffres->add($reservationOffre);
        }

        return $this;
    }

    public function removeReservationOffre(ReservationOffre $reservationOffre): self
    {
        $this->reservationOffres->removeElement($reservationOffre);

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function addUser(User $user): self
    {
        if (!$this->users->contains($user)) {
            $this->users->add($user);
        }

        return $this;
    }

    public function removeUser(User $user): self
    {
        $this->users->removeElement($user);

        return $this;
    }
}
