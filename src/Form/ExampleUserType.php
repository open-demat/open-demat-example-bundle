<?php

namespace OpenDemat\ExampleBundle\Form;

use OpenDemat\Core\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ExampleUserType extends AbstractType
{
    public const ROLE_CHOICES = [
        'Gestionnaire achats internes' => 'ROLE_EXAMPLE_GESTIONNAIRE',
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = (bool) $options['is_edit'];

        $builder
            ->add('username', TextType::class, [
                'label' => 'Identifiant',
                'disabled' => $isEdit,
            ])
            ->add('email', EmailType::class, [
                'label' => 'Adresse e-mail',
                'required' => false,
            ])
            ->add('roleExample', ChoiceType::class, [
                'label' => 'Roles Example',
                'mapped' => false,
                'choices' => self::ROLE_CHOICES,
                'placeholder' => false,
                'multiple' => true,
                'expanded' => true,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'is_edit' => false,
        ]);
    }
}
