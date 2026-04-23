<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

/**
 * @see https://symfony.com/doc/current/forms.html#creating-forms-in-controllers
 * helps you understand what the hell I'm doing
 */
class NewExtractionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('term', ChoiceType::class, [
                'label' => 'Term',
                'label_attr' => ['class' => 'filter-label'],
                'choice_attr' => ['class' => 'filter-label'],
                'row_attr' => ['class' => 'form-row'],
                'attr' => ['class' => 'form-input', 'placeholder' => 'Paste Canvas access token here'],
            ])
            ->add('department', ChoiceType::class, [
                'label_html' => true,
                'label' => 'Degree Program',
                'label_attr' => ['class' => 'filter-label'],
                'attr' => ['class' => 'form-input'],
                'choices' => [
                    'Computer Systems Engineering (CSE)'=>0, 
                    'Compuster Science (CS)'=>1,
                ]
            ])
            ->add('submit', SubmitType::class, [
                'label_html' => true,
                'label' => 'Load Courses',
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
