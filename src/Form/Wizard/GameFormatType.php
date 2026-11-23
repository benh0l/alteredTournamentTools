<?php

declare(strict_types=1);

namespace App\Form\Wizard;

use App\Entity\Tournament;
use App\Enum\TournamentFormat;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Step 3: Game format (Constructed/Limited).
 */
final class GameFormatType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('format', EnumType::class, [
                'class' => TournamentFormat::class,
                'label' => 'Quel format de jeu ?',
                'expanded' => true,
                'choice_label' => fn (TournamentFormat $format) => $format->getLabel(),
                'constraints' => [
                    new Assert\NotNull(message: 'Le format de jeu est requis'),
                ],
            ])
            ->add('teamSize', ChoiceType::class, [
                'label' => 'tournament.wizard.step3.team_size_question',
                'choices' => Tournament::TEAM_SIZE_CHOICES,
                'expanded' => true,
                'data' => $options['data']['teamSize'] ?? 1,
                'constraints' => [
                    new Assert\NotNull(),
                    new Assert\Choice(choices: Tournament::TEAM_SIZE_CHOICES),
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
