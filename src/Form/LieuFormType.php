<?php

namespace App\Form;

use App\Entity\Lieu;
use App\Entity\Offre;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;

class LieuFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // -------------------------------------------------------------------
            // Informations principales
            // -------------------------------------------------------------------
            ->add('nom', TextType::class, [
                'label'    => 'Nom du lieu *',
                'attr'     => [
                    'placeholder' => 'ex: Café des Arts',
                    'class'       => 'form-control',
                    'autofocus'   => true,
                ],
            ])

            ->add('ville', TextType::class, [
                'label' => 'Ville *',
                'attr'  => [
                    'placeholder' => 'ex: Tunis',
                    'class'       => 'form-control',
                ],
            ])

            ->add('adresse', TextType::class, [
                'label'    => 'Adresse',
                'required' => false,
                'attr'     => [
                    'placeholder' => 'ex: 12, Avenue Habib Bourguiba',
                    'class'       => 'form-control',
                ],
            ])

            // -------------------------------------------------------------------
            // Contacts & liens
            // -------------------------------------------------------------------
            ->add('telephone', TextType::class, [
                'label'    => 'Téléphone',
                'required' => false,
                'attr'     => [
                    'placeholder' => 'ex: +216 20 123 456',
                    'class'       => 'form-control',
                ],
            ])

            ->add('siteWeb', UrlType::class, [
                'label'           => 'Site web',
                'required'        => false,
                'default_protocol' => 'https',
                'attr'            => [
                    'placeholder' => 'https://monsite.tn',
                    'class'       => 'form-control',
                ],
            ])

            ->add('instagram', TextType::class, [
                'label'    => 'Instagram',
                'required' => false,
                'attr'     => [
                    'placeholder' => 'https://www.instagram.com/compte',
                    'class'       => 'form-control',
                ],
            ])

            // -------------------------------------------------------------------
            // Description
            // -------------------------------------------------------------------
            ->add('description', TextareaType::class, [
                'label'    => 'Description',
                'required' => false,
                'attr'     => [
                    'rows'        => 4,
                    'placeholder' => 'Décrivez ce lieu...',
                    'class'       => 'form-control',
                ],
            ])

            // -------------------------------------------------------------------
            // Budget
            // -------------------------------------------------------------------
            ->add('budgetMin', NumberType::class, [
                'label'    => 'Budget minimum (TND)',
                'required' => false,
                'scale'    => 2,
                'attr'     => [
                    'placeholder' => '0.00',
                    'class'       => 'form-control',
                    'min'         => 0,
                    'step'        => '0.01',
                ],
            ])

            ->add('budgetMax', NumberType::class, [
                'label'    => 'Budget maximum (TND)',
                'required' => false,
                'scale'    => 2,
                'attr'     => [
                    'placeholder' => '0.00',
                    'class'       => 'form-control',
                    'min'         => 0,
                    'step'        => '0.01',
                ],
            ])

            // -------------------------------------------------------------------
            // Catégorie & Type (enums)
            // -------------------------------------------------------------------
            ->add('categorie', ChoiceType::class, [
                'label'       => 'Catégorie *',
                'choices'     => Lieu::CATEGORIES,
                'placeholder' => '-- Choisir une catégorie --',
                'attr'        => ['class' => 'form-control'],
            ])

            ->add('type', ChoiceType::class, [
                'label'       => 'Type *',
                'choices'     => Lieu::TYPES,
                'placeholder' => '-- Choisir un type --',
                'attr'        => ['class' => 'form-control'],
            ])

            // -------------------------------------------------------------------
            // Coordonnées GPS
            // -------------------------------------------------------------------
            ->add('latitude', NumberType::class, [
                'label'    => 'Latitude',
                'required' => false,
                'scale'    => 7,
                'attr'     => [
                    'placeholder' => 'ex: 36.8190',
                    'class'       => 'form-control',
                    'step'        => 'any',
                ],
            ])

            ->add('longitude', NumberType::class, [
                'label'    => 'Longitude',
                'required' => false,
                'scale'    => 7,
                'attr'     => [
                    'placeholder' => 'ex: 10.1658',
                    'class'       => 'form-control',
                    'step'        => 'any',
                ],
            ])

            // -------------------------------------------------------------------
            // Image & Offre
            // -------------------------------------------------------------------
            ->add('imageUrl', TextType::class, [
                'label'    => 'URL de l\'image',
                'required' => false,
                'attr'     => [
                    'placeholder' => 'https://example.com/image.jpg',
                    'class'       => 'form-control',
                ],
            ])

            ->add('offre', EntityType::class, [
                'class'        => Offre::class,
                'choice_label' => 'id',
                'label'        => 'Offre associée',
                'required'     => false,
                'placeholder'  => '-- Aucune offre --',
                'attr'         => ['class' => 'form-control'],
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
