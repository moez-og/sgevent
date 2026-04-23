<?php

namespace App\Form;

use App\Entity\Evenement;
use App\Entity\Lieu;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\PositiveOrZero;

class EvenementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', TextType::class, [
                'label' => 'Titre de l\'événement',
                'constraints' => [
                    new NotBlank(),
                    new Length(['max' => 140]),
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['rows' => 5],
            ])
            ->add('dateDebut', DateTimeType::class, [
                'label' => 'Date et heure de début',
                'widget' => 'single_text',
                'constraints' => [new NotBlank()],
            ])
            ->add('dateFin', DateTimeType::class, [
                'label' => 'Date et heure de fin',
                'widget' => 'single_text',
                'constraints' => [new NotBlank()],
            ])
            ->add('capaciteMax', IntegerType::class, [
                'label' => 'Capacité maximale',
                'constraints' => [
                    new NotBlank(['message' => 'La capacité maximale est obligatoire.']),
                    new PositiveOrZero(['message' => 'La capacité maximale doit être positive ou nulle.']),
                ],
            ])
            ->add('prix', MoneyType::class, [
                'label' => 'Prix d\'entrée (TND)',
                'currency' => 'TND',
                'constraints' => [new PositiveOrZero()],
            ])
            ->add('lieu', EntityType::class, [
                'label' => 'Lieu de l\'événement',
                'class' => Lieu::class,
                'choice_label' => 'nom',
                'required' => false,
                'placeholder' => '-- Sélectionner un lieu --',
            ])
            ->add('statut', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'Ouvert' => 'OUVERT',
                    'Fermé' => 'FERME',
                    'Annulé' => 'ANNULE',
                ],
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type d\'événement',
                'choices' => [
                    'Public' => 'PUBLIC',
                    'Privé' => 'PRIVE',
                ],
            ])
            ->add('imageUrl', UrlType::class, [
                'label' => 'URL de l\'image',
                'required' => false,
            ])
            ->add('imageFile', FileType::class, [
                'label' => 'Uploader une image',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '4M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                            'image/gif',
                        ],
                        'mimeTypesMessage' => 'Veuillez uploader une image valide (JPG, PNG, WEBP, GIF).',
                    ]),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Evenement::class,
        ]);
    }
}
