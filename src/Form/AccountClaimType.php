<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * Form for claiming a guest account.
 *
 * Allows setting pseudo and password to convert a guest account to a full account.
 */
final class AccountClaimType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('pseudo', TextType::class, [
                'label' => 'form.claim.pseudo',
                'attr' => [
                    'placeholder' => 'form.claim.pseudo_placeholder',
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
            ->add('password', RepeatedType::class, [
                'type' => PasswordType::class,
                'first_options' => [
                    'label' => 'form.claim.password',
                    'attr' => [
                        'autocomplete' => 'new-password',
                        'placeholder' => 'form.claim.password_placeholder',
                    ],
                ],
                'second_options' => [
                    'label' => 'form.claim.confirm_password',
                    'attr' => [
                        'autocomplete' => 'new-password',
                        'placeholder' => 'form.claim.confirm_password_placeholder',
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
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'account_claim',
        ]);
    }
}
