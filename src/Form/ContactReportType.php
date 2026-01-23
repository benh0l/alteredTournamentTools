<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\ContactReport;
use App\Enum\ContactSubject;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ContactReportType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('subject', EnumType::class, [
                'class' => ContactSubject::class,
                'label' => 'contact.form.subject',
                'choice_label' => fn (ContactSubject $subject) => $this->translator->trans($subject->getLabel()),
                'attr' => [
                    'class' => 'form-input',
                ],
            ])
            ->add('message', TextareaType::class, [
                'label' => 'contact.form.message',
                'attr' => [
                    'class' => 'form-input',
                    'rows' => 5,
                    'placeholder' => 'contact.form.message_placeholder',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'contact.message.not_blank',
                    ]),
                    new Length([
                        'min' => 10,
                        'max' => 2000,
                        'minMessage' => 'contact.message.min_length',
                        'maxMessage' => 'contact.message.max_length',
                    ]),
                ],
            ])
            ->add('pageUrl', HiddenType::class, [
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ContactReport::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'contact_report',
        ]);
    }
}
