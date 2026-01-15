<?php

declare(strict_types=1);

namespace App\Form\Wizard;

use App\Enum\MatchFormat;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Step 5: Match format (BO1/BO3).
 */
final class MatchFormatType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('swissMatchFormat', EnumType::class, [
                'class' => MatchFormat::class,
                'label' => 'Format des matchs (rondes suisses)',
                'expanded' => true,
                'choice_label' => fn (MatchFormat $format) => $format->getLabel(),
                'constraints' => [
                    new Assert\NotNull(message: 'Le format des matchs est requis'),
                ],
            ])
            ->add('eliminationMatchFormat', EnumType::class, [
                'class' => MatchFormat::class,
                'label' => 'Format des matchs (elimination)',
                'expanded' => true,
                'required' => false,
                'placeholder' => false,
                'choice_label' => fn (MatchFormat $format) => $format->getLabel(),
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
