<?php

namespace App\Form;

use App\Model\ParticipationRequestData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ParticipationRequestType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $maxPlaces = (int) $options['max_places'];
        $questionCount = (int) $options['question_count'];

        $builder
            ->add('nb_places', IntegerType::class, [
                'property_path' => 'nbPlaces',
                'required' => true,
                'constraints' => [
                    new Assert\LessThanOrEqual([
                        'value' => max(1, $maxPlaces),
                        'message' => 'Le nombre de places demandees depasse les places restantes.',
                    ]),
                ],
            ])
            ->add('contact_prefer', ChoiceType::class, [
                'property_path' => 'contactPrefer',
                'required' => true,
                'choices' => [
                    'Telephone' => 'TELEPHONE',
                    'Email' => 'EMAIL',
                ],
            ])
            ->add('contact_value', TextType::class, [
                'property_path' => 'contactValue',
                'required' => true,
            ])
            ->add('commentaire', TextareaType::class, [
                'property_path' => 'commentaire',
                'required' => false,
            ])
            ->add('reponses', CollectionType::class, [
                'property_path' => 'reponses',
                'required' => $questionCount > 0,
                'entry_type' => TextType::class,
                'allow_add' => true,
                'allow_delete' => false,
                'prototype' => false,
                'entry_options' => [
                    'required' => $questionCount > 0,
                    'constraints' => $questionCount > 0
                        ? [new Assert\NotBlank(['message' => 'Veuillez repondre a toutes les questions obligatoires.'])]
                        : [],
                ],
                'constraints' => $questionCount > 0
                    ? [new Assert\Count([
                        'min' => $questionCount,
                        'max' => $questionCount,
                        'minMessage' => 'Veuillez repondre a toutes les questions obligatoires.',
                        'maxMessage' => 'Veuillez repondre a toutes les questions obligatoires.',
                    ])]
                    : [],
            ]);

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($questionCount): void {
            $data = $event->getData();
            if (!$data instanceof ParticipationRequestData || $questionCount <= 0) {
                return;
            }

            $answers = $data->getReponses();
            for ($i = 0; $i < $questionCount; $i++) {
                if (!array_key_exists($i, $answers)) {
                    $answers[$i] = '';
                }
            }

            ksort($answers);
            $data->setReponses(array_values($answers));
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ParticipationRequestData::class,
            'csrf_protection' => true,
            'max_places' => 1,
            'question_count' => 0,
        ]);

        $resolver->setAllowedTypes('max_places', 'int');
        $resolver->setAllowedTypes('question_count', 'int');
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}
