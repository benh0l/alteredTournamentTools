<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\ApiAccessRequest;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Url;

final class ApiAccessRequestType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('projectName', TextType::class, [
                'label' => 'api.form.project_name',
                'attr' => [
                    'class' => 'form-input',
                    'placeholder' => 'api.form.project_name_placeholder',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'api.validation.project_name.not_blank',
                    ]),
                    new Length([
                        'min' => 3,
                        'max' => 100,
                        'minMessage' => 'api.validation.project_name.min_length',
                        'maxMessage' => 'api.validation.project_name.max_length',
                    ]),
                ],
            ])
            ->add('intendedUse', TextareaType::class, [
                'label' => 'api.form.intended_use',
                'attr' => [
                    'class' => 'form-input',
                    'rows' => 5,
                    'placeholder' => 'api.form.intended_use_placeholder',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'api.validation.intended_use.not_blank',
                    ]),
                    new Length([
                        'min' => 20,
                        'max' => 2000,
                        'minMessage' => 'api.validation.intended_use.min_length',
                        'maxMessage' => 'api.validation.intended_use.max_length',
                    ]),
                ],
            ])
            ->add('websiteUrl', UrlType::class, [
                'label' => 'api.form.website_url',
                'required' => false,
                'attr' => [
                    'class' => 'form-input',
                    'placeholder' => 'https://example.com',
                ],
                'constraints' => [
                    new Url([
                        'message' => 'api.validation.website_url.invalid',
                        'requireTld' => true,
                    ]),
                ],
            ])
            ->add('discordUsername', TextType::class, [
                'label' => 'api.form.discord_username',
                'required' => false,
                'attr' => [
                    'class' => 'form-input',
                    'placeholder' => 'api.form.discord_username_placeholder',
                ],
                'constraints' => [
                    new Length([
                        'max' => 100,
                        'maxMessage' => 'api.validation.discord_username.max_length',
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ApiAccessRequest::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'api_access_request',
        ]);
    }
}
