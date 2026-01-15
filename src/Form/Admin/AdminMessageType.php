<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Form type for admin messaging (FR71).
 */
class AdminMessageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('recipientType', ChoiceType::class, [
                'label' => 'Type de destinataire',
                'choices' => [
                    'Un organisateur specifique' => 'individual',
                    'Tous les organisateurs' => 'all_organizers',
                ],
                'expanded' => true,
                'data' => 'individual',
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Veuillez choisir un type de destinataire.']),
                ],
            ])
            ->add('recipient', EntityType::class, [
                'class' => User::class,
                'choices' => $options['organizers'],
                'choice_label' => fn (User $user) => $user->getPseudo() . ' (' . $user->getEmail() . ')',
                'label' => 'Destinataire',
                'placeholder' => 'Selectionnez un organisateur...',
                'required' => false,
                'attr' => [
                    'data-admin-message-target' => 'recipientField',
                ],
            ])
            ->add('subject', TextType::class, [
                'label' => 'Sujet',
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le sujet est requis.']),
                    new Assert\Length([
                        'max' => 255,
                        'maxMessage' => 'Le sujet ne peut pas depasser {{ limit }} caracteres.',
                    ]),
                ],
                'attr' => [
                    'placeholder' => 'Sujet du message...',
                ],
            ])
            ->add('body', TextareaType::class, [
                'label' => 'Message',
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le message est requis.']),
                ],
                'attr' => [
                    'rows' => 10,
                    'placeholder' => 'Votre message...',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'organizers' => [],
        ]);

        $resolver->setAllowedTypes('organizers', 'array');
    }
}
