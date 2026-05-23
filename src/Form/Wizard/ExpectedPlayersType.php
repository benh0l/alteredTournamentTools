<?php

declare(strict_types=1);

namespace App\Form\Wizard;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Step 6: Expected players and rounds configuration.
 *
 * Also includes group stage configuration for GROUP_STAGE_ELIMINATION tournaments.
 */
final class ExpectedPlayersType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('expectedPlayers', IntegerType::class, [
                'label' => 'Nombre de joueurs attendus',
                'attr' => [
                    'class' => 'form-input w-full',
                    'min' => 4,
                    'max' => 256,
                    'placeholder' => 'Ex: 16',
                ],
                'constraints' => [
                    new Assert\NotNull(message: 'Le nombre de joueurs est requis'),
                    new Assert\Range(
                        min: 4,
                        max: 256,
                        notInRangeMessage: 'Le nombre de joueurs doit etre entre {{ min }} et {{ max }}'
                    ),
                ],
            ])
            ->add('swissRounds', IntegerType::class, [
                'label' => 'Nombre de rondes suisses',
                'required' => false,
                'attr' => [
                    'class' => 'form-input w-full',
                    'min' => 1,
                    'max' => 10,
                ],
                'help' => 'Laissez vide pour utiliser la suggestion automatique',
            ])
            ->add('topCutSize', ChoiceType::class, [
                'label' => 'Taille du Top Cut',
                'required' => false,
                'placeholder' => 'Pas de Top Cut',
                'choices' => [
                    'Top 4' => 4,
                    'Top 8' => 8,
                    'Top 16' => 16,
                    'Top 32' => 32,
                ],
                'attr' => [
                    'class' => 'form-select w-full',
                ],
            ])
            // Group stage configuration fields (shown only for GROUP_STAGE_ELIMINATION)
            ->add('groupCount', IntegerType::class, [
                'label' => 'Nombre de poules',
                'required' => false,
                'attr' => [
                    'class' => 'form-input w-full',
                    'min' => 2,
                    'max' => 16,
                    'placeholder' => 'Ex: 4',
                ],
                'constraints' => [
                    new Assert\Range(
                        min: 2,
                        max: 16,
                        notInRangeMessage: 'Le nombre de poules doit etre entre {{ min }} et {{ max }}'
                    ),
                ],
            ])
            ->add('playersPerGroup', IntegerType::class, [
                'label' => 'Joueurs par poule',
                'required' => false,
                'attr' => [
                    'class' => 'form-input w-full',
                    'min' => 3,
                    'max' => 16,
                    'placeholder' => 'Ex: 4',
                ],
                'constraints' => [
                    new Assert\Range(
                        min: 3,
                        max: 16,
                        notInRangeMessage: 'Le nombre de joueurs par poule doit etre entre {{ min }} et {{ max }}'
                    ),
                ],
            ])
            ->add('qualifiersPerGroup', ChoiceType::class, [
                'label' => 'Qualifies par poule',
                'required' => false,
                'placeholder' => 'Choisir...',
                'choices' => [
                    'tournament.wizard.step6.qualifiers_choice.1' => 1,
                    'tournament.wizard.step6.qualifiers_choice.2' => 2,
                    'tournament.wizard.step6.qualifiers_choice.3' => 3,
                    'tournament.wizard.step6.qualifiers_choice.4' => 4,
                ],
                'choice_translation_domain' => 'messages',
                'attr' => [
                    'class' => 'form-select w-full',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
