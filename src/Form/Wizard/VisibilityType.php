<?php

declare(strict_types=1);

namespace App\Form\Wizard;

use App\Enum\TournamentVisibility;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Step 7: Tournament visibility (Public/Private).
 */
final class VisibilityType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('visibility', EnumType::class, [
                'class' => TournamentVisibility::class,
                'label' => 'Visibilite du tournoi',
                'expanded' => true,
                'choice_label' => fn (TournamentVisibility $visibility) => $visibility->getLabel(),
                'constraints' => [
                    new Assert\NotNull(message: 'La visibilite est requise'),
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
