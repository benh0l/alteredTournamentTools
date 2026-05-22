<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class PseudoChoiceFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $suggestions = $options['suggestions'] ?? [];
        $originalPseudo = $options['original_pseudo'] ?? '';

        $choices = [];
        foreach ($suggestions as $suggestion) {
            $choices[$suggestion] = $suggestion;
        }
        $choices['Autre (saisir ci-dessous)'] = '_custom';

        $builder
            ->add('pseudo_choice', ChoiceType::class, [
                'label' => 'Choisissez un pseudo disponible',
                'choices' => $choices,
                'expanded' => true,
                'data' => $suggestions[0] ?? '_custom',
                'help' => sprintf('Le pseudo "%s" est déjà utilisé.', $originalPseudo),
            ])
            ->add('custom_pseudo', TextType::class, [
                'label' => 'Ou saisissez un pseudo personnalisé',
                'required' => false,
                'constraints' => [
                    new Length([
                        'min' => 3,
                        'max' => 50,
                        'minMessage' => 'Le pseudo doit contenir au moins {{ limit }} caractères.',
                        'maxMessage' => 'Le pseudo ne peut pas dépasser {{ limit }} caractères.',
                    ]),
                    new Regex([
                        'pattern' => '/^[a-zA-Z0-9_-]+$/',
                        'message' => 'Le pseudo ne peut contenir que des lettres, chiffres, tirets et underscores.',
                    ]),
                ],
                'attr' => [
                    'placeholder' => 'Votre pseudo personnalisé',
                ],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Créer mon compte',
                'attr' => ['class' => 'btn btn-primary'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'suggestions' => [],
            'original_pseudo' => '',
        ]);

        $resolver->setAllowedTypes('suggestions', 'array');
        $resolver->setAllowedTypes('original_pseudo', 'string');
    }
}
