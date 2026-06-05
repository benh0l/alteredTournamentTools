<?php

declare(strict_types=1);

namespace App\Form\Wizard;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class RecurrenceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('enabled', CheckboxType::class, [
                'required' => false,
                'label' => 'tournament.wizard.recurrence.enabled',
            ])
            ->add('unit', ChoiceType::class, [
                'choices' => [
                    'tournament.wizard.recurrence.unit.days' => 'days',
                    'tournament.wizard.recurrence.unit.weeks' => 'weeks',
                    'tournament.wizard.recurrence.unit.months' => 'months',
                ],
                'required' => false,
                'label' => 'tournament.wizard.recurrence.unit_label',
            ])
            ->add('interval', IntegerType::class, [
                'required' => false,
                'label' => 'tournament.wizard.recurrence.interval_label',
                'attr' => ['min' => 1, 'max' => 52],
                'constraints' => [
                    new Assert\Range(min: 1, max: 52, notInRangeMessage: 'tournament.wizard.recurrence.interval_range'),
                ],
            ])
            ->add('count', IntegerType::class, [
                'required' => false,
                'label' => 'tournament.wizard.recurrence.count_label',
                'attr' => ['min' => 2, 'max' => 52],
                'constraints' => [
                    new Assert\Range(min: 2, max: 52, notInRangeMessage: 'tournament.wizard.recurrence.count_range'),
                ],
            ])
            ->add('publishImmediately', ChoiceType::class, [
                'choices' => [
                    'tournament.wizard.recurrence.draft' => false,
                    'tournament.wizard.recurrence.publish_now' => true,
                ],
                'expanded' => true,
                'multiple' => false,
                'required' => true,
                'label' => 'tournament.wizard.recurrence.publish_label',
                'data' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'csrf_protection' => false,
        ]);
    }
}
