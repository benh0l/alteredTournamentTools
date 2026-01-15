<?php

declare(strict_types=1);

namespace App\Form\Wizard;

use App\Enum\TournamentFormat;
use Symfony\Component\Form\AbstractType;
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
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
