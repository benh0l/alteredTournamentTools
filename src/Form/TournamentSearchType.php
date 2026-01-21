<?php

declare(strict_types=1);

namespace App\Form;

use App\Enum\TournamentFormat;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form type for tournament search (FR59, FR60).
 */
class TournamentSearchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('location', TextType::class, [
                'label' => 'Ville ou code postal',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ex: Paris, 75001, Lyon...',
                    'class' => 'form-input',
                    'data-address-autocomplete-target' => 'input',
                    'autocomplete' => 'off',
                ],
            ])
            ->add('radius', NumberType::class, [
                'label' => 'Rayon (km)',
                'required' => false,
                'data' => 50,
                'attr' => [
                    'class' => 'form-input',
                    'placeholder' => '50',
                    'min' => 1,
                    'max' => 1000,
                ],
            ])
            ->add('format', ChoiceType::class, [
                'label' => 'Format',
                'required' => false,
                'placeholder' => 'Tous les formats',
                'choices' => [
                    'Construit Standard' => TournamentFormat::CONSTRUCTED_STANDARD->value,
                    'Construit Singleton' => TournamentFormat::CONSTRUCTED_SINGLETON->value,
                    'Construit NUC' => TournamentFormat::CONSTRUCTED_NUC->value,
                    '(Fun) Heros Out of Faction' => TournamentFormat::CONSTRUCTED_HERO_OUT_OF_FACTION->value,
                    '(Fun) Bifaction' => TournamentFormat::CONSTRUCTED_BIFACTION->value,
                    'Limite' => TournamentFormat::LIMITED->value,
                ],
                'attr' => [
                    'class' => 'form-select',
                ],
            ])
            ->add('dateFrom', DateType::class, [
                'label' => 'Du',
                'required' => false,
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-input',
                ],
            ])
            ->add('dateTo', DateType::class, [
                'label' => 'Au',
                'required' => false,
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-input',
                ],
            ])
            // Hidden fields for coordinates (set by JavaScript/geocoding)
            ->add('lat', HiddenType::class, [
                'attr' => [
                    'data-address-autocomplete-target' => 'latitude',
                ],
            ])
            ->add('lng', HiddenType::class, [
                'attr' => [
                    'data-address-autocomplete-target' => 'longitude',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method' => 'GET',
            'csrf_protection' => false, // GET forms don't need CSRF
        ]);
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}
