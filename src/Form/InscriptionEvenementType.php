<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class InscriptionEvenementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $maxTickets = $options['max_tickets'] ?? 5;

        $choices = [];
        for ($i = 1; $i <= $maxTickets; $i++) {
            $choices[sprintf('%d ticket%s', $i, $i > 1 ? 's' : '')] = $i;
        }

        $builder
            ->add('nbTickets', ChoiceType::class, [
                'label' => 'Nombre de tickets',
                'choices' => $choices,
                'data' => 1,
                'attr' => [
                    'class' => 'form-select',
                ],
                'help' => 'Choisissez le nombre de places que vous désirez réserver',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'max_tickets' => 5,
        ]);

        $resolver->setAllowedTypes('max_tickets', 'int');
    }
}
