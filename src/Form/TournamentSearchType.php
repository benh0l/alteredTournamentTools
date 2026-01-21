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
                'label' => 'tournament.search.location',
                'required' => false,
                'attr' => [
                    'placeholder' => 'tournament.search.location_placeholder',
                    'class' => 'form-input',
                    'data-address-autocomplete-target' => 'input',
                    'autocomplete' => 'off',
                ],
            ])
            ->add('radius', NumberType::class, [
                'label' => 'tournament.search.radius',
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
                'label' => 'tournament.search.format',
                'required' => false,
                'placeholder' => 'tournament.search.all_formats',
                'choices' => [
                    'enum.tournament_format.constructed_standard' => TournamentFormat::CONSTRUCTED_STANDARD->value,
                    'enum.tournament_format.constructed_singleton' => TournamentFormat::CONSTRUCTED_SINGLETON->value,
                    'enum.tournament_format.constructed_nuc' => TournamentFormat::CONSTRUCTED_NUC->value,
                    'enum.tournament_format.constructed_hero_oof' => TournamentFormat::CONSTRUCTED_HERO_OUT_OF_FACTION->value,
                    'enum.tournament_format.constructed_bifaction' => TournamentFormat::CONSTRUCTED_BIFACTION->value,
                    'enum.tournament_format.limited' => TournamentFormat::LIMITED->value,
                ],
                'attr' => [
                    'class' => 'form-select',
                ],
            ])
            ->add('dateFrom', DateType::class, [
                'label' => 'tournament.search.date_from',
                'required' => false,
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-input',
                ],
            ])
            ->add('dateTo', DateType::class, [
                'label' => 'tournament.search.date_to',
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
