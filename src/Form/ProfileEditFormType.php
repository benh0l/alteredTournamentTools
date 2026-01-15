<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

final class ProfileEditFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('pseudo', TextType::class, [
                'label' => 'Pseudo',
                'attr' => [
                    'class' => 'form-input',
                    'autocomplete' => 'username',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Le pseudo est requis',
                    ]),
                    new Length([
                        'min' => 3,
                        'max' => 50,
                        'minMessage' => 'Le pseudo doit contenir au moins {{ limit }} caracteres',
                        'maxMessage' => 'Le pseudo ne peut pas depasser {{ limit }} caracteres',
                    ]),
                    new Regex([
                        'pattern' => '/^[a-zA-Z0-9_-]+$/',
                        'message' => 'Le pseudo ne peut contenir que des lettres, chiffres, tirets et underscores',
                    ]),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Adresse email',
                'attr' => [
                    'class' => 'form-input',
                    'autocomplete' => 'email',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'L\'email est requis',
                    ]),
                    new Email([
                        'message' => 'Veuillez entrer un email valide',
                    ]),
                ],
            ])
            ->add('name', TextType::class, [
                'label' => 'Nom complet (optionnel)',
                'required' => false,
                'attr' => [
                    'class' => 'form-input',
                    'autocomplete' => 'name',
                ],
                'constraints' => [
                    new Length([
                        'max' => 100,
                        'maxMessage' => 'Le nom ne peut pas depasser {{ limit }} caracteres',
                    ]),
                ],
            ])
            ->add('avatarFile', FileType::class, [
                'label' => 'Photo de profil',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'accept' => 'image/jpeg,image/png,image/gif,image/webp',
                ],
                'constraints' => [
                    new File([
                        'maxSize' => '2M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/gif',
                            'image/webp',
                        ],
                        'mimeTypesMessage' => 'Veuillez uploader une image valide (JPG, PNG, GIF ou WebP)',
                    ]),
                ],
            ])
            ->add('teamTag', TextType::class, [
                'label' => 'Tag de team',
                'required' => false,
                'attr' => [
                    'class' => 'form-input',
                    'maxlength' => 20,
                    'placeholder' => 'Ex: MyTeam',
                ],
                'constraints' => [
                    new Length([
                        'max' => 20,
                        'maxMessage' => 'Le tag ne peut pas depasser {{ limit }} caracteres',
                    ]),
                    new Regex([
                        'pattern' => '/^[a-zA-Z0-9_-]*$/',
                        'message' => 'Le tag ne peut contenir que des lettres, chiffres, tirets et underscores',
                    ]),
                ],
            ])
            ->add('currentPassword', PasswordType::class, [
                'label' => 'Mot de passe actuel (requis pour changer l\'email)',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'autocomplete' => 'current-password',
                    'class' => 'form-input',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'profile_edit',
        ]);
    }
}
