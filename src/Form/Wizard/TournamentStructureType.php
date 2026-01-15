<?php

declare(strict_types=1);

namespace App\Form\Wizard;

use App\Enum\TournamentStructure;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Step 4: Tournament structure (Swiss/Elimination/Mixed).
 */
final class TournamentStructureType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('structure', EnumType::class, [
                'class' => TournamentStructure::class,
                'label' => 'Quelle structure de tournoi ?',
                'expanded' => true,
                'choice_label' => fn (TournamentStructure $structure) => $structure->getLabel(),
                'constraints' => [
                    new Assert\NotNull(message: 'La structure du tournoi est requise'),
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
