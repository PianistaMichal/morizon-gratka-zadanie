<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class PhoenixTokenType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('phoenix_token', TextType::class, [
            'required' => false,
            'constraints' => [
                new NotBlank(message: 'Token cannot be empty.', allowNull: true),
                new Length(
                    max: 255,
                    maxMessage: 'Token cannot be longer than {{ limit }} characters.',
                ),
            ],
            'attr' => [
                'placeholder' => 'Wpisz token dostępu do PhoenixApi...',
                'autocomplete' => 'off',
                'class' => 'token-input',
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'phoenix_token',
        ]);
    }
}
