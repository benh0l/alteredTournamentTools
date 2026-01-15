<?php

declare(strict_types=1);

namespace App\Form\Wizard;

use App\Enum\DecklistTransparency;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Step 8: Decklist transparency (Open/Closed).
 */
final class DecklistTransparencyType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('decklistTransparency', EnumType::class, [
                'class' => DecklistTransparency::class,
                'label' => 'Transparence des decklists',
                'expanded' => true,
                'choice_label' => fn (DecklistTransparency $transparency) => $transparency->getLabel(),
                'constraints' => [
                    new Assert\NotNull(message: 'La transparence des decklists est requise'),
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
