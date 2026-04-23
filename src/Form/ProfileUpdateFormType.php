<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class ProfileUpdateFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('prenom', TextType::class, [
                'label' => 'Prenom',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Le prenom est obligatoire.',
                    ]),
                    new Length([
                        'min' => 2,
                        'max' => 50,
                        'minMessage' => 'Le prenom doit contenir au moins {{ limit }} caracteres.',
                        'maxMessage' => 'Le prenom ne peut pas depasser {{ limit }} caracteres.',
                    ]),
                    new Regex([
                        'pattern' => '/^[A-Za-zÀ-ÿ\s\-\']+$/u',
                        'message' => 'Le prenom contient des caracteres invalides.',
                    ]),
                ],
            ])
            ->add('nom', TextType::class, [
                'label' => 'Nom',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Le nom est obligatoire.',
                    ]),
                    new Length([
                        'min' => 2,
                        'max' => 50,
                        'minMessage' => 'Le nom doit contenir au moins {{ limit }} caracteres.',
                        'maxMessage' => 'Le nom ne peut pas depasser {{ limit }} caracteres.',
                    ]),
                    new Regex([
                        'pattern' => '/^[A-Za-zÀ-ÿ\s\-\']+$/u',
                        'message' => 'Le nom contient des caracteres invalides.',
                    ]),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'constraints' => [
                    new NotBlank([
                        'message' => 'L\'email est obligatoire.',
                    ]),
                    new Email([
                        'message' => 'Le format de l\'email est invalide.',
                    ]),
                    new Length([
                        'max' => 180,
                        'maxMessage' => 'L\'email ne peut pas depasser {{ limit }} caracteres.',
                    ]),
                ],
            ])
            ->add('telephone', TextType::class, [
                'label' => 'Telephone',
                'required' => false,
                'constraints' => [
                    new Length([
                        'max' => 20,
                        'maxMessage' => 'Le telephone ne peut pas depasser {{ limit }} caracteres.',
                    ]),
                    new Regex([
                        'pattern' => '/^(\+?\d[\d\s\-]{7,15})$/',
                        'message' => 'Numero de telephone invalide.',
                    ]),
                ],
            ])
            ->add('image', FileType::class, [
                'label' => 'Photo de profil (optionnel)',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '2M',
                        'mimeTypes' => ['image/jpeg', 'image/png', 'image/webp'],
                        'mimeTypesMessage' => 'Image invalide. Formats acceptes: JPG, PNG, WEBP.',
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'csrf_token_id' => 'profile_update_form',
        ]);
    }
}
