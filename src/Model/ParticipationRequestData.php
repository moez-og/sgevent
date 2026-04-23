<?php

namespace App\Model;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class ParticipationRequestData
{
    #[Assert\NotNull(message: 'Le nombre de places doit etre strictement positif.')]
    #[Assert\Positive(message: 'Le nombre de places doit etre strictement positif.')]
    private ?int $nbPlaces = 1;

    #[Assert\NotBlank(message: 'Le contact de reponse est obligatoire.')]
    #[Assert\Choice(choices: ['TELEPHONE', 'EMAIL'], message: 'Le mode de contact est invalide.')]
    private string $contactPrefer = 'EMAIL';

    #[Assert\NotBlank(message: 'Le contact de reponse est obligatoire.')]
    private string $contactValue = '';

    private ?string $commentaire = null;

    /**
     * @var array<int, string>
     */
    private array $reponses = [];

    public function getNbPlaces(): ?int
    {
        return $this->nbPlaces;
    }

    public function setNbPlaces(?int $nbPlaces): self
    {
        $this->nbPlaces = $nbPlaces;

        return $this;
    }

    public function getContactPrefer(): string
    {
        return $this->contactPrefer;
    }

    public function setContactPrefer(string $contactPrefer): self
    {
        $this->contactPrefer = strtoupper(trim($contactPrefer));

        return $this;
    }

    public function getContactValue(): string
    {
        return $this->contactValue;
    }

    public function setContactValue(string $contactValue): self
    {
        $this->contactValue = trim($contactValue);

        return $this;
    }

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): self
    {
        $commentaire = trim((string) $commentaire);
        $this->commentaire = $commentaire !== '' ? $commentaire : null;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getReponses(): array
    {
        return $this->reponses;
    }

    /**
     * @param array<int, mixed> $reponses
     */
    public function setReponses(array $reponses): self
    {
        $normalized = [];
        foreach ($reponses as $index => $reponse) {
            $normalized[(int) $index] = trim((string) $reponse);
        }

        ksort($normalized);
        $this->reponses = array_values($normalized);

        return $this;
    }

    #[Assert\Callback]
    public function validateContact(ExecutionContextInterface $context): void
    {
        $mode = $this->getContactPrefer();
        $value = trim($this->getContactValue());

        if ($mode === 'EMAIL') {
            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $context->buildViolation('Veuillez saisir une adresse email valide.')
                    ->atPath('contactValue')
                    ->addViolation();
            }

            return;
        }

        if ($mode === 'TELEPHONE' && !preg_match('/^\d{8}$/', $value)) {
            $context->buildViolation('Le numero de telephone doit contenir exactement 8 chiffres.')
                ->atPath('contactValue')
                ->addViolation();
        }
    }
}
