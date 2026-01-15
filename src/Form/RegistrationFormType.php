<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

final class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'form.user.email',
                'attr' => [
                    'placeholder' => 'form.user.email_placeholder',
                    'autocomplete' => 'email',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'validation.user.email_required']),
                    new Email(['message' => 'validation.user.email_invalid']),
                ],
            ])
            ->add('pseudo', TextType::class, [
                'label' => 'form.user.pseudo',
                'attr' => [
                    'placeholder' => 'form.user.pseudo_placeholder',
                    'autocomplete' => 'username',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'validation.user.pseudo_required']),
                    new Length([
                        'min' => 3,
                        'max' => 50,
                        'minMessage' => 'validation.user.pseudo_min',
                        'maxMessage' => 'validation.user.pseudo_max',
                    ]),
                    new Regex([
                        'pattern' => '/^[a-zA-Z0-9_-]+$/',
                        'message' => 'validation.user.pseudo_format',
                    ]),
                ],
            ])
            ->add('name', TextType::class, [
                'label' => 'form.user.name',
                'required' => false,
                'attr' => [
                    'placeholder' => 'form.user.name_placeholder',
                    'autocomplete' => 'name',
                ],
                'constraints' => [
                    new Length([
                        'max' => 100,
                        'maxMessage' => 'validation.user.name_max',
                    ]),
                ],
            ])
            ->add('avatarFile', FileType::class, [
                'label' => 'form.user.avatar',
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
                        'mimeTypesMessage' => 'validation.user.avatar_invalid',
                    ]),
                ],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'first_options' => [
                    'label' => 'form.user.password',
                    'attr' => [
                        'autocomplete' => 'new-password',
                        'placeholder' => 'form.user.password_placeholder',
                    ],
                ],
                'second_options' => [
                    'label' => 'form.user.confirm_password',
                    'attr' => [
                        'autocomplete' => 'new-password',
                        'placeholder' => 'form.user.confirm_password_placeholder',
                    ],
                ],
                'invalid_message' => 'validation.user.passwords_mismatch',
                'constraints' => [
                    new NotBlank(['message' => 'validation.user.password_required']),
                    new Length([
                        'min' => 8,
                        'minMessage' => 'validation.user.password_min',
                        'max' => 4096, // Prevent DoS
                    ]),
                ],
            ])
            ->add('agreeTerms', CheckboxType::class, [
                'label' => 'form.user.agree_terms',
                'mapped' => false,
                'constraints' => [
                    new IsTrue(['message' => 'validation.user.terms_required']),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'registration',
        ]);
    }
}
