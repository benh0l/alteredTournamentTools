<?php

declare(strict_types=1);

namespace App\Form\Wizard;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Step 2: Date and location.
 */
final class DateLocationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('date', DateType::class, [
                'label' => 'Date du tournoi',
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-input w-full',
                    'min' => (new \DateTime())->format('Y-m-d'),
                ],
                'constraints' => [
                    new Assert\NotNull(message: 'La date du tournoi est requise'),
                    new Assert\GreaterThanOrEqual(
                        value: 'today',
                        message: 'La date doit etre aujourd\'hui ou dans le futur'
                    ),
                ],
            ])
            ->add('time', TimeType::class, [
                'label' => 'Heure de debut',
                'widget' => 'single_text',
                'required' => false,
                'attr' => [
                    'class' => 'form-input w-full',
                ],
            ])
            ->add('location', TextType::class, [
                'label' => 'Lieu',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ex: Game Store Paris, 123 rue du Jeu',
                    'class' => 'form-input w-full',
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
