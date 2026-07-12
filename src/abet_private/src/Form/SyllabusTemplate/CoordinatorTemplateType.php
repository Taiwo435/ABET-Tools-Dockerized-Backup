<?php

namespace App\Form\SyllabusTemplate;

use App\Entity\Program;
use App\Entity\SyllabusTemplate\DeliveryType;
use App\Form\Model\CoordinatorTemplateData;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Positive;

/** @extends AbstractType<CoordinatorTemplateData> */
final class CoordinatorTemplateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ($options['include_course_identity']) {
            $builder
                ->add('program', EntityType::class, [
                    'class' => Program::class,
                    'choice_label' => static fn (Program $program): string => sprintf('%s (%s %s)', $program->getName(), $program->getCode(), $program->getYear()),
                    'placeholder' => 'Select a program',
                ])
                ->add('courseSubject', TextType::class, ['label' => 'Course subject'])
                ->add('courseNumber', TextType::class, ['label' => 'Course number'])
                ->add('courseName', TextType::class, ['label' => 'Course name'])
                ->add('deliveryType', EnumType::class, [
                    'class' => DeliveryType::class,
                    'choice_label' => static fn (DeliveryType $type): string => match ($type) {
                        DeliveryType::InPerson => 'In person',
                        DeliveryType::Hybrid => 'Hybrid',
                        DeliveryType::Online => 'Online',
                    },
                ]);
        }

        $builder
            ->add('creditHours', NumberType::class, [
                'required' => false,
                'label' => 'Credit hours',
                'constraints' => [new Positive()],
            ])
            ->add('courseCoordinators', TextareaType::class, [
                'required' => false,
                'label' => 'Course coordinators',
                'help' => 'Enter one coordinator per line.',
            ])
            ->add('creditCategorization', TextType::class, ['required' => false, 'label' => 'Credit categorization'])
            ->add('catalogDescription', TextareaType::class, ['required' => false, 'label' => 'Catalog description'])
            ->add('courseOutcomes', TextareaType::class, [
                'required' => false,
                'label' => 'Course outcomes',
                'help' => 'Enter one outcome per line.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CoordinatorTemplateData::class,
            'include_course_identity' => false,
        ]);
        $resolver->setAllowedTypes('include_course_identity', 'bool');
    }
}
