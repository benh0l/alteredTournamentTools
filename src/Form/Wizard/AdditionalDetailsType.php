<?php

declare(strict_types=1);

namespace App\Form\Wizard;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Step 9: Additional details (description, entry fee, prizes, link).
 */
final class AdditionalDetailsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'class' => 'form-textarea w-full',
                    'rows' => 4,
                    'placeholder' => 'Decrivez votre tournoi...',
                ],
            ])
            ->add('entryFee', TextType::class, [
                'label' => 'Prix d\'entree',
                'required' => false,
                'attr' => [
                    'class' => 'form-input w-full',
                    'placeholder' => 'Ex: 10€, Gratuit',
                ],
            ])
            ->add('prizes', TextareaType::class, [
                'label' => 'Dotations',
                'required' => false,
                'attr' => [
                    'class' => 'form-textarea w-full',
                    'rows' => 3,
                    'placeholder' => 'Ex: 1er: Booster Box, 2e: Starter Deck...',
                ],
            ])
            ->add('alteredGgLink', UrlType::class, [
                'label' => 'Lien Altered.gg',
                'required' => false,
                'attr' => [
                    'class' => 'form-input w-full',
                    'placeholder' => 'https://altered.gg/...',
                ],
                'constraints' => [
                    new Assert\Url(message: 'L\'URL n\'est pas valide'),
                ],
            ])
            ->add('isTumult', CheckboxType::class, [
                'label' => 'Tournoi Tumult',
                'required' => false,
                'attr' => [
                    'class' => 'form-checkbox',
                ],
                'label_attr' => [
                    'class' => 'form-checkbox-label',
                ],
                'help' => 'Cochez si ce tournoi est un evenement Tumult',
            ])
            ->add('isSeasonFinalsQualifier', CheckboxType::class, [
                'label' => 'Qualifier Season Finals',
                'required' => false,
                'attr' => [
                    'class' => 'form-checkbox',
                ],
                'label_attr' => [
                    'class' => 'form-checkbox-label',
                ],
                'help' => 'Cochez si ce tournoi qualifie pour les Season Finals',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
