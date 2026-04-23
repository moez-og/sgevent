<?php

namespace App\Form;

use App\Entity\AnnonceSortie;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class AnnonceSortieType extends AbstractType
{
    private const VILLES_TUNISIE = [
        'Tunis', 'Ariana', 'Ben Arous', 'Manouba', 'Nabeul', 'Zaghouan', 'Bizerte', 'Beja',
        'Jendouba', 'Kef', 'Siliana', 'Sousse', 'Monastir', 'Mahdia', 'Sfax', 'Kairouan',
        'Kasserine', 'Sidi Bouzid', 'Gabes', 'Medenine', 'Tataouine', 'Gafsa', 'Tozeur', 'Kebili',
    ];

    private const TYPES_ACTIVITE = [
        'Randonnée',
        'Plage',
        'Road trip',
        'Camping',
        'Pique-nique',
        'Sport',
        'Visite culturelle',
        'Soirée',
        'Restaurant',
        'Cinéma',

        'Autre',
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', TextType::class, [
                'label' => 'Titre',
                'attr' => ['maxlength' => 140, 'placeholder' => 'Ex: Sortie plage a Sousse'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description (facultatif)',
                'required' => false,
                'attr' => [
                    'rows' => 4,
                    'placeholder' => 'Ajoutez une description courte de votre sortie',
                ],
            ])
            ->add('ville', ChoiceType::class, [
                'label' => 'Ville',
                'placeholder' => 'Choisir une ville',
                'choices' => array_combine(self::VILLES_TUNISIE, self::VILLES_TUNISIE),
            ])
            ->add('lieu_texte', TextType::class, [
                'label' => 'Lieu',
                'attr' => ['placeholder' => 'Ex: Marina de Sidi Bou Said'],
            ])
            ->add('point_rencontre', HiddenType::class, [
                'label' => false,
                'attr' => ['data-role' => 'point-rencontre'],
            ])
            ->add('type_activite', ChoiceType::class, [
                'label' => 'Type activite',
                'choices' => [
                    'Randonnee' => 'Randonnee',
                    'Plage' => 'Plage',
                    'Road trip' => 'Road trip',
                    'Camping' => 'Camping',
                    'Pique-nique' => 'Pique-nique',
                    'Sport' => 'Sport',
                    'Visite culturelle' => 'Visite culturelle',
                    'Soiree' => 'Soiree',
                    'Restaurant' => 'Restaurant',
                    'Cinéma' => 'Cinéma',
                    'Autre' => '__autre__',
                ],
            ])
            ->add('type_activite_autre', TextType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Autre activite',
                'attr' => [
                    'placeholder' => 'Ecrire une activite personnalisee',
                ],
            ])
            ->add('date_sortie', DateTimeType::class, [
                'label' => 'Date sortie',
                'widget' => 'single_text',
                'html5' => false,
                'format' => 'yyyy-MM-dd HH:mm',
                'attr' => [
                    'class' => 'js-sortie-date',
                    'placeholder' => 'AAAA-MM-JJ HH:MM',
                    'data-min-days' => '1',
                    'autocomplete' => 'off',
                ],
            ])
            ->add('budget_gratuit', CheckboxType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Gratuit',
            ])
            ->add('budget_max', MoneyType::class, [
                'label' => 'Budget max',
                'currency' => 'TND',
                'scale' => 2,
                'attr' => [
                    'step' => '0.01',
                    'min' => 0,
                    'placeholder' => 'Ex: 35.00',
                ],
            ])
            ->add('nb_places', IntegerType::class, [
                'label' => 'Nombre de places',
                'attr' => ['min' => 1],
            ])
            ->add('imageFile', FileType::class, [
                'mapped' => false,
                'required' => $options['require_image'],
                'label' => 'Image',
                'constraints' => [
                    new File(maxSize: '5M', mimeTypes: ['image/jpeg', 'image/png', 'image/webp']),
                ],
                'attr' => ['accept' => 'image/png,image/jpeg,image/webp'],
            ])
            ->add('questions_json', HiddenType::class, [
                'required' => false,
                'empty_data' => '[]',
                'attr' => ['data-role' => 'questions-json'],
            ]);

        if ($options['is_admin']) {
            $builder->add('statut', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'OUVERTE' => 'OUVERTE',
                    'CLOTUREE' => 'CLOTUREE',
                    'ANNULEE' => 'ANNULEE',
                    'TERMINEE' => 'TERMINEE',
                ],
            ]);
        }

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event): void {
            $data = $event->getData();
            if (!is_array($data)) {
                return;
            }

            if (($data['type_activite'] ?? '') === '__autre__') {
                $custom = trim((string) ($data['type_activite_autre'] ?? ''));
                if ($custom !== '') {
                    $data['type_activite'] = mb_substr($custom, 0, 80);
                }
            }

            $isFree = isset($data['budget_gratuit']) && in_array($data['budget_gratuit'], ['1', 'on', 1, true], true);
            if ($isFree) {
                $data['budget_max'] = '0';
            }

            $questionsRaw = trim((string) ($data['questions_json'] ?? ''));
            if ($questionsRaw === '') {
                $data['questions_json'] = '[]';
            }

            $event->setData($data);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AnnonceSortie::class,
            'is_admin' => false,
            'require_image' => false,
        ]);

        $resolver->setAllowedTypes('is_admin', 'bool');
        $resolver->setAllowedTypes('require_image', 'bool');
    }
}
