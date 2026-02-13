<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Tournament;
use App\Enum\DecklistTransparency;
use App\Enum\MatchFormat;
use App\Enum\TournamentFormat;
use App\Enum\TournamentStructure;
use App\Enum\TournamentVisibility;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Url;
use Symfony\Contracts\Translation\TranslatorInterface;

final class TournamentType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // Basic Information
            ->add('name', TextType::class, [
                'label' => 'form.tournament.name',
                'attr' => [
                    'placeholder' => 'form.tournament.name_placeholder',
                    'class' => 'form-input',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'validation.tournament.name_required']),
                    new Length([
                        'min' => 3,
                        'max' => 100,
                        'minMessage' => 'validation.tournament.name_min',
                        'maxMessage' => 'validation.tournament.name_max',
                    ]),
                ],
            ])
            ->add('date', DateType::class, [
                'label' => 'form.tournament.date',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-input'],
                'constraints' => [
                    new NotBlank(['message' => 'validation.tournament.date_required']),
                ],
            ])
            ->add('time', TimeType::class, [
                'label' => 'form.tournament.time',
                'widget' => 'single_text',
                'required' => false,
                'attr' => ['class' => 'form-input'],
            ])
            ->add('location', TextType::class, [
                'label' => 'form.tournament.location',
                'required' => false,
                'attr' => [
                    'placeholder' => 'form.tournament.location_placeholder',
                    'class' => 'form-input',
                    'data-address-autocomplete-target' => 'input',
                ],
            ])
            ->add('latitude', HiddenType::class, [
                'required' => false,
                'attr' => [
                    'data-address-autocomplete-target' => 'latitude',
                ],
            ])
            ->add('longitude', HiddenType::class, [
                'required' => false,
                'attr' => [
                    'data-address-autocomplete-target' => 'longitude',
                ],
            ])

            // Game Configuration
            ->add('format', EnumType::class, [
                'class' => TournamentFormat::class,
                'label' => 'form.tournament.format',
                'choice_label' => fn (TournamentFormat $format) => $this->translator->trans($format->getLabel()),
                'attr' => ['class' => 'form-select'],
                'constraints' => [
                    new NotBlank(['message' => 'validation.tournament.format_required']),
                ],
            ])
            ->add('structure', EnumType::class, [
                'class' => TournamentStructure::class,
                'label' => 'form.tournament.structure',
                'choice_label' => fn (TournamentStructure $structure) => $this->translator->trans($structure->getLabel()),
                'attr' => [
                    'class' => 'form-select',
                    'data-tournament-form-target' => 'structure',
                    'data-action' => 'change->tournament-form#structureChanged',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'validation.tournament.structure_required']),
                ],
            ])

            // Match Format Configuration
            ->add('swissMatchFormat', EnumType::class, [
                'class' => MatchFormat::class,
                'label' => 'form.tournament.swiss_match_format',
                'choice_label' => fn (MatchFormat $format) => $this->translator->trans($format->getLabel()),
                'attr' => ['class' => 'form-select'],
                'constraints' => [
                    new NotBlank(['message' => 'validation.tournament.swiss_format_required']),
                ],
            ])
            ->add('eliminationMatchFormat', EnumType::class, [
                'class' => MatchFormat::class,
                'label' => 'form.tournament.elimination_match_format',
                'choice_label' => fn (MatchFormat $format) => $this->translator->trans($format->getLabel()),
                'required' => false,
                'placeholder' => 'form.common.select',
                'attr' => [
                    'class' => 'form-select',
                    'data-tournament-form-target' => 'eliminationFormat',
                ],
            ])

            // Round Configuration
            ->add('expectedPlayers', IntegerType::class, [
                'label' => 'form.tournament.expected_players',
                'required' => false,
                'attr' => [
                    'min' => 4,
                    'max' => 256,
                    'placeholder' => 'form.tournament.expected_players_placeholder',
                    'class' => 'form-input',
                    'data-tournament-form-target' => 'expectedPlayers',
                    'data-action' => 'change->tournament-form#suggestRounds',
                ],
                'constraints' => [
                    new Range([
                        'min' => 4,
                        'max' => 256,
                        'notInRangeMessage' => 'validation.tournament.players_range',
                    ]),
                ],
            ])
            ->add('swissRounds', IntegerType::class, [
                'label' => 'form.tournament.swiss_rounds',
                'required' => false,
                'attr' => [
                    'min' => 1,
                    'max' => 10,
                    'class' => 'form-input',
                    'data-tournament-form-target' => 'swissRounds',
                ],
                'constraints' => [
                    new Range([
                        'min' => 1,
                        'max' => 10,
                        'notInRangeMessage' => 'validation.tournament.rounds_range',
                    ]),
                ],
            ])
            ->add('topCutSize', ChoiceType::class, [
                'label' => 'form.tournament.top_cut_size',
                'choices' => [
                    'Top 4' => 4,
                    'Top 8' => 8,
                    'Top 16' => 16,
                    'Top 32' => 32,
                ],
                'required' => false,
                'placeholder' => 'form.common.select',
                'attr' => [
                    'class' => 'form-select',
                    'data-tournament-form-target' => 'topCutSize',
                ],
            ])

            // Visibility & Transparency
            ->add('visibility', EnumType::class, [
                'class' => TournamentVisibility::class,
                'label' => 'form.tournament.visibility',
                'expanded' => true,
                'choice_label' => fn (TournamentVisibility $v) => $this->translator->trans(
                    $v === TournamentVisibility::PUBLIC
                        ? 'enum.tournament_visibility.public_desc'
                        : 'enum.tournament_visibility.private_desc'
                ),
                'constraints' => [
                    new NotBlank(['message' => 'validation.tournament.visibility_required']),
                ],
            ])
            ->add('decklistTransparency', EnumType::class, [
                'class' => DecklistTransparency::class,
                'label' => 'form.tournament.decklist_transparency',
                'expanded' => true,
                'choice_label' => fn (DecklistTransparency $d) => $this->translator->trans($d->getLabel()),
                'constraints' => [
                    new NotBlank(['message' => 'validation.tournament.decklist_transparency_required']),
                ],
            ])

            // Additional Information
            ->add('description', TextareaType::class, [
                'label' => 'form.tournament.description',
                'required' => false,
                'attr' => [
                    'rows' => 4,
                    'placeholder' => 'form.tournament.description_placeholder',
                    'class' => 'form-textarea',
                ],
            ])
            ->add('entryFee', TextType::class, [
                'label' => 'form.tournament.entry_fee',
                'required' => false,
                'attr' => [
                    'placeholder' => 'form.tournament.entry_fee_placeholder',
                    'class' => 'form-input',
                ],
            ])
            ->add('prizes', TextareaType::class, [
                'label' => 'form.tournament.prizes',
                'required' => false,
                'attr' => [
                    'rows' => 3,
                    'placeholder' => 'form.tournament.prizes_placeholder',
                    'class' => 'form-textarea',
                ],
            ])
            ->add('alteredGgLink', UrlType::class, [
                'label' => 'form.tournament.altered_gg_link',
                'required' => false,
                'attr' => [
                    'placeholder' => 'form.tournament.altered_gg_placeholder',
                    'class' => 'form-input',
                ],
                'constraints' => [
                    new Url(['message' => 'validation.url_invalid']),
                ],
            ])

            // Tournament Type Flags
            ->add('isTumult', CheckboxType::class, [
                'label' => 'form.tournament.is_tumult',
                'required' => false,
                'attr' => [
                    'class' => 'form-checkbox',
                ],
                'label_attr' => [
                    'class' => 'form-checkbox-label',
                ],
                'help' => 'form.tournament.is_tumult_help',
            ])
            ->add('isSeasonFinalsQualifier', CheckboxType::class, [
                'label' => 'form.tournament.is_season_finals_qualifier',
                'required' => false,
                'attr' => [
                    'class' => 'form-checkbox',
                ],
                'label_attr' => [
                    'class' => 'form-checkbox-label',
                ],
                'help' => 'form.tournament.is_season_finals_qualifier_help',
            ])
            ->add('checkInEnabled', CheckboxType::class, [
                'label' => 'form.tournament.check_in_enabled',
                'required' => false,
                'attr' => [
                    'class' => 'form-checkbox',
                ],
                'label_attr' => [
                    'class' => 'form-checkbox-label',
                ],
                'help' => 'form.tournament.check_in_enabled_help',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Tournament::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'tournament_create',
        ]);
    }
}
