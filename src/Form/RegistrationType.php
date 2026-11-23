<?php

declare(strict_types=1);

namespace App\Form;

use App\Enum\Faction;
use App\Enum\TournamentFormat;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
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

        $this->addDeckFields($builder, '', $format, [
            'faction' => $options['initial_faction'],
            'faction2' => $options['initial_faction2'],
            'hero' => $options['initial_hero'],
            'decklistUrl' => $options['initial_decklist_url'],
        ]);

        if (!$options['team']) {
            return;
        }

        if ($options['partner_editable']) {
            $builder->add('partnerPseudo', TextType::class, [
                'label' => 'form.registration.partner_pseudo',
                'required' => true,
                'help' => 'form.registration.partner_pseudo_help',
                'attr' => ['class' => 'form-input w-full', 'autocomplete' => 'off'],
                'constraints' => [
                    new Assert\NotBlank(message: 'validation.partner_required'),
                ],
            ]);
        }

        $this->addDeckFields($builder, 'partner', $format, [
            'faction' => $options['initial_partner_faction'],
            'faction2' => $options['initial_partner_faction2'],
            'hero' => $options['initial_partner_hero'],
            'decklistUrl' => $options['initial_partner_decklist_url'],
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
            // 2v2: adds the partner's deck fields
            'team' => false,
            // 2v2: adds the partner pseudo field (registration only, the partner can't be changed afterwards)
            'partner_editable' => false,
            'initial_partner_faction' => null,
            'initial_partner_faction2' => null,
            'initial_partner_hero' => null,
            'initial_partner_decklist_url' => null,
        ]);

        $resolver->setAllowedTypes('tournament_format', ['null', TournamentFormat::class]);
        $resolver->setAllowedTypes('team', 'bool');
        $resolver->setAllowedTypes('partner_editable', 'bool');
    }

    /**
     * Add faction / faction2 / hero / decklist fields, optionally prefixed (e.g. "partnerFaction").
     *
     * @param array{faction: ?string, faction2: ?string, hero: ?string, decklistUrl: ?string} $initial
     */
    private function addDeckFields(FormBuilderInterface $builder, string $prefix, ?TournamentFormat $format, array $initial): void
    {
        $name = static fn (string $field): string => $prefix === '' ? $field : $prefix . ucfirst($field);

        $builder
            ->add($name('faction'), ChoiceType::class, [
                'label' => 'form.registration.faction',
                'required' => false,
                'placeholder' => 'form.registration.select_faction',
                'choices' => Faction::getChoices(),
                'data' => $initial['faction'],
                'attr' => [
                    'class' => 'form-input w-full',
                    'data-registration-form-target' => 'faction',
                    'data-action' => 'change->registration-form#factionChanged',
                ],
            ])
            ->add($name('faction2'), ChoiceType::class, [
                'label' => 'form.registration.faction2',
                'required' => false,
                'placeholder' => 'form.registration.select_second_faction',
                'choices' => Faction::getChoices(),
                'data' => $initial['faction2'],
                'attr' => [
                    'class' => 'form-input w-full',
                    'data-registration-form-target' => 'faction2',
                    'data-action' => 'change->registration-form#factionChanged',
                ],
            ])
            ->add($name('hero'), ChoiceType::class, [
                'label' => 'form.registration.hero',
                'required' => false,
                'placeholder' => 'form.registration.select_faction_first',
                'choices' => array_flip(Faction::getAllHeroes($format)),
                'data' => $initial['hero'],
                'attr' => [
                    'class' => 'form-input w-full',
                    'data-registration-form-target' => 'hero',
                ],
            ])
            ->add($name('decklistUrl'), UrlType::class, [
                'label' => 'form.registration.decklist_url',
                'required' => false,
                'data' => $initial['decklistUrl'],
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
}
