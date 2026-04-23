<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\SortieRecapRepository;

#[ORM\Entity(repositoryClass: SortieRecapRepository::class)]
#[ORM\Table(name: 'sortie_recap')]
class SortieRecap
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

    #[ORM\OneToOne(targetEntity: AnnonceSortie::class, inversedBy: 'sortieRecap')]
    #[ORM\JoinColumn(name: 'sortie_id', referencedColumnName: 'id', unique: true)]
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

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $video_path = null;

    public function getVideo_path(): ?string
    {
        return $this->video_path;
    }

    public function setVideo_path(string $video_path): self
    {
        $this->video_path = $video_path;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: false)]
    private ?\DateTimeInterface $generated_at = null;

    public function getGenerated_at(): ?\DateTimeInterface
    {
        return $this->generated_at;
    }

    public function setGenerated_at(\DateTimeInterface $generated_at): self
    {
        $this->generated_at = $generated_at;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: false)]
    private ?int $version = null;

    public function getVersion(): ?int
    {
        return $this->version;
    }

    public function setVersion(int $version): self
    {
        $this->version = $version;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $version_label = null;

    public function getVersion_label(): ?string
    {
        return $this->version_label;
    }

    public function setVersion_label(?string $version_label): self
    {
        $this->version_label = $version_label;
        return $this;
    }

    public function getVideoPath(): ?string
    {
        return $this->video_path;
    }

    public function setVideoPath(string $video_path): static
    {
        $this->video_path = $video_path;

        return $this;
    }

    public function getGeneratedAt(): ?\DateTime
    {
        return $this->generated_at;
    }

    public function setGeneratedAt(\DateTime $generated_at): static
    {
        $this->generated_at = $generated_at;

        return $this;
    }

    public function getVersionLabel(): ?string
    {
        return $this->version_label;
    }

    public function setVersionLabel(?string $version_label): static
    {
        $this->version_label = $version_label;

        return $this;
    }

}
