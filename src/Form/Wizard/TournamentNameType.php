<?php

declare(strict_types=1);

namespace App\Form\Wizard;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Step 1: Tournament name.
 */
final class TournamentNameType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Quel est le nom de votre tournoi ?',
                'attr' => [
                    'placeholder' => 'Ex: Tournoi du Printemps',
                    'class' => 'form-input w-full text-lg',
                    'autofocus' => true,
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le nom du tournoi est requis'),
                    new Assert\Length(
                        min: 3,
                        max: 100,
                        minMessage: 'Le nom doit faire au moins 3 caracteres',
                        maxMessage: 'Le nom ne peut pas depasser 100 caracteres'
                    ),
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
