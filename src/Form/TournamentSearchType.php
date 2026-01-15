<?php

declare(strict_types=1);

namespace App\Form;

use App\Enum\TournamentFormat;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
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
                ],
            ])
            ->add('radius', ChoiceType::class, [
                'label' => 'Rayon',
                'choices' => [
                    '5 km' => 5,
                    '10 km' => 10,
                    '20 km' => 20,
                    '50 km' => 50,
                    '100 km' => 100,
                ],
                'data' => 50,
                'attr' => [
                    'class' => 'form-select',
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
            ->add('dateRange', ChoiceType::class, [
                'label' => 'Periode',
                'required' => false,
                'placeholder' => 'Toutes les dates',
                'choices' => [
                    'Aujourd\'hui' => 'today',
                    'Cette semaine' => 'week',
                    'Ce mois' => 'month',
                    'Personnalise' => 'custom',
                ],
                'attr' => [
                    'class' => 'form-select',
                    'data-action' => 'change->filter#toggleCustomDates',
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
            ->add('lat', HiddenType::class)
            ->add('lng', HiddenType::class);
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
