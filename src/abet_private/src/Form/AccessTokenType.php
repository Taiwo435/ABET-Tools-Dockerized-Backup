<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

class AccessTokenType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('token', TextType::class, [
                'label' => 'CANVAS ACCESS TOKEN',
                'label_attr' => ['class' => 'form-label'],
                'row_attr' => ['class' => 'form-row'],
                'attr' => ['class' => 'form-input', 'placeholder' => 'Paste Canvas access token here'],
            ])
            ->add('submit', SubmitType::class, [
                'label_html' => true,
                'label' => '<span class="btn-icon">🔗</span> Validate Token',
                'attr' => ['class' => 'btn btn-primary'],
            ])
        ;
        
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // Configure your form options here
        ]);
    }
}
