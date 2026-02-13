<?php

declare(strict_types=1);

namespace App\Form;

use App\Enum\Faction;
use App\Enum\TournamentFormat;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class RegistrationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var TournamentFormat|null $format */
        $format = $options['tournament_format'];

        $builder
            ->add('faction', ChoiceType::class, [
                'label' => 'form.registration.faction',
                'required' => false,
                'placeholder' => 'form.registration.select_faction',
                'choices' => Faction::getChoices(),
                'data' => $options['initial_faction'],
                'attr' => [
                    'class' => 'form-input w-full',
                    'data-registration-form-target' => 'faction',
                    'data-action' => 'change->registration-form#factionChanged',
                ],
            ])
            ->add('faction2', ChoiceType::class, [
                'label' => 'form.registration.faction2',
                'required' => false,
                'placeholder' => 'form.registration.select_second_faction',
                'choices' => Faction::getChoices(),
                'data' => $options['initial_faction2'],
                'attr' => [
                    'class' => 'form-input w-full',
                    'data-registration-form-target' => 'faction2',
                    'data-action' => 'change->registration-form#factionChanged',
                ],
            ])
            ->add('hero', ChoiceType::class, [
                'label' => 'form.registration.hero',
                'required' => false,
                'placeholder' => 'form.registration.select_faction_first',
                'choices' => array_flip(Faction::getAllHeroes($format)),
                'data' => $options['initial_hero'],
                'attr' => [
                    'class' => 'form-input w-full',
                    'data-registration-form-target' => 'hero',
                ],
            ])
            ->add('decklistUrl', UrlType::class, [
                'label' => 'form.registration.decklist_url',
                'required' => false,
                'data' => $options['initial_decklist_url'],
                'attr' => [
                    'placeholder' => 'form.registration.decklist_placeholder',
                    'class' => 'form-input w-full',
                ],
                'help' => 'form.registration.decklist_help',
                'constraints' => [
                    new Assert\Url([
                        'message' => 'validation.url_invalid',
                    ]),
                    new Assert\Regex([
                        'pattern' => '/^https?:\/\/(www\.)?altered\.gg\/.*$/i',
                        'message' => 'validation.decklist_url_invalid',
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'registration',
            'initial_faction' => null,
            'initial_faction2' => null,
            'initial_hero' => null,
            'initial_decklist_url' => null,
            'tournament_format' => null,
        ]);

        $resolver->setAllowedTypes('tournament_format', ['null', TournamentFormat::class]);
    }
}
