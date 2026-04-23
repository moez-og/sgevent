<?php

namespace App\Form;

use App\Entity\Lieu;
use App\Entity\Offre;
use App\Enum\LieuCategorie;
use App\Enum\LieuType as LieuTypeEnum;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;

class LieuType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('offre', EntityType::class, [
                'class' => Offre::class,
                'choice_label' => 'titre',
                'label' => 'Offre associée',
                'placeholder' => 'Aucune offre',
                'required' => false,
                'attr' => ['class' => 'form-select'],
            ])
            ->add('nom', TextType::class, [
                'label' => 'Nom *',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Nom du lieu',
                ],
            ])
            ->add('ville', TextType::class, [
                'label' => 'Ville *',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ville',
                ],
            ])
            ->add('adresse', TextType::class, [
                'label' => 'Adresse',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Adresse complète',
                ],
            ])
            ->add('telephone', TextType::class, [
                'label' => 'Téléphone',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '+216 ...',
                ],
            ])
            ->add('siteWeb', UrlType::class, [
                'label' => 'Site web',
                'required' => false,
                'default_protocol' => 'https',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'https://...',
                ],
            ])
            ->add('instagram', TextType::class, [
                'label' => 'Instagram',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '@compte_instagram',
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 5,
                    'placeholder' => 'Décrivez le lieu...',
                ],
            ])
            ->add('budgetMin', NumberType::class, [
                'label' => 'Budget minimum (TND)',
                'required' => false,
                'scale' => 2,
                'html5' => false,
                'attr' => [
                    'class' => 'form-control',
                    'step' => '0.01',
                    'min' => 0,
                    'placeholder' => '0.00',
                ],
            ])
            ->add('budgetMax', NumberType::class, [
                'label' => 'Budget maximum (TND)',
                'required' => false,
                'scale' => 2,
                'html5' => false,
                'attr' => [
                    'class' => 'form-control',
                    'step' => '0.01',
                    'min' => 0,
                    'placeholder' => '0.00',
                ],
            ])
            ->add('categorie', ChoiceType::class, [
                'label' => 'Catégorie *',
                'choices' => LieuCategorie::choices(),
                'placeholder' => '-- Choisir une catégorie --',
                'attr' => [
                    'class' => 'form-select',
                ],
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type *',
                'choices' => LieuTypeEnum::choices(),
                'placeholder' => '-- Choisir un type --',
                'attr' => [
                    'class' => 'form-select',
                ],
            ])
            ->add('latitude', NumberType::class, [
                'label' => 'Latitude',
                'required' => false,
                'scale' => 7,
                'html5' => false,
                'attr' => [
                    'class' => 'form-control',
                    'step' => 'any',
                    'placeholder' => '36.7993',
                ],
            ])
            ->add('longitude', NumberType::class, [
                'label' => 'Longitude',
                'required' => false,
                'scale' => 7,
                'html5' => false,
                'attr' => [
                    'class' => 'form-control',
                    'step' => 'any',
                    'placeholder' => '10.1696',
                ],
            ])
            ->add('imageUrl', TextType::class, [
                'label' => 'URL de l\'image',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'https://... ou chemin local',
                ],
            ])
            ->add('imageFile', FileType::class, [
                'label' => 'Téléverser une image',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new Image([
                        'maxSize' => '5M',
                        'mimeTypesMessage' => 'Veuillez téléverser une image valide.',
                    ]),
                ],
                'attr' => [
                    'class' => 'form-control',
                    'accept' => 'image/*',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Lieu::class,
        ]);
    }
}