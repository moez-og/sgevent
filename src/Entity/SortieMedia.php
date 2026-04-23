<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\SortieMediaRepository;

#[ORM\Entity(repositoryClass: SortieMediaRepository::class)]
#[ORM\Table(name: 'sortie_media')]
class SortieMedia
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

    #[ORM\ManyToOne(targetEntity: AnnonceSortie::class, inversedBy: 'sortieMedias')]
    #[ORM\JoinColumn(name: 'sortie_id', referencedColumnName: 'id')]
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

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'sortieMedias')]
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

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $file_path = null;

    public function getFile_path(): ?string
    {
        return $this->file_path;
    }

    public function setFile_path(string $file_path): self
    {
        $this->file_path = $file_path;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $media_type = null;

    public function getMedia_type(): ?string
    {
        return $this->media_type;
    }

    public function setMedia_type(string $media_type): self
    {
        $this->media_type = $media_type;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: false)]
    private ?\DateTimeInterface $uploaded_at = null;

    public function getUploaded_at(): ?\DateTimeInterface
    {
        return $this->uploaded_at;
    }

    public function setUploaded_at(\DateTimeInterface $uploaded_at): self
    {
        $this->uploaded_at = $uploaded_at;
        return $this;
    }

    public function getFilePath(): ?string
    {
        return $this->file_path;
    }

    public function setFilePath(string $file_path): static
    {
        $this->file_path = $file_path;

        return $this;
    }

    public function getMediaType(): ?string
    {
        return $this->media_type;
    }

    public function setMediaType(string $media_type): static
    {
        $this->media_type = $media_type;

        return $this;
    }

    public function getUploadedAt(): ?\DateTime
    {
        return $this->uploaded_at;
    }

    public function setUploadedAt(\DateTime $uploaded_at): static
    {
        $this->uploaded_at = $uploaded_at;

        return $this;
    }

}
