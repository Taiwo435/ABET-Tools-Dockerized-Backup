<?php

namespace App\Form\SyllabusTemplate;

use App\Entity\Program;
use App\Entity\SyllabusTemplate\DeliveryType;
use App\Form\Model\CoordinatorTemplateData;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
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
                ->add('courseSubject', TextType::class, [
                    'label' => 'Course subject',
                    'empty_data' => '',
                    'constraints' => [new NotBlank()],
                ])
                ->add('courseNumber', TextType::class, [
                    'label' => 'Course number',
                    'empty_data' => '',
                    'constraints' => [new NotBlank()],
                ])
                ->add('courseName', TextType::class, [
                    'label' => 'Course name',
                    'empty_data' => '',
                    'constraints' => [new NotBlank()],
                ]);
        }

        if ($options['include_course_identity'] || $options['include_offering_identity']) {
            $builder
                ->add('deliveryType', EnumType::class, [
                    'class' => DeliveryType::class,
                    'choice_label' => static fn (DeliveryType $type): string => match ($type) {
                        DeliveryType::InPerson => 'In person',
                        DeliveryType::Hybrid => 'Hybrid',
                        DeliveryType::Online => 'Online',
                    },
                ]);
        }

        if ($options['include_offering_identity']) {
            $builder
                ->add('academicYear', TextType::class, [
                    'label' => 'Academic year',
                    'help' => 'For example: 2026-2027.',
                    'empty_data' => '',
                    'constraints' => [new NotBlank()],
                ])
                ->add('term', TextType::class, [
                    'label' => 'Term',
                    'help' => 'For example: Fall, Spring, or Summer.',
                    'empty_data' => '',
                    'constraints' => [new NotBlank()],
                ])
                ->add('section', TextType::class, [
                    'required' => false,
                    'label' => 'Section',
                    'empty_data' => '',
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
                'empty_data' => '',
            ])
            ->add('contactHours', TextType::class, [
                'required' => false,
                'label' => 'Contact hours',
                'help' => 'For example: 3 hours lecture and 1 hour laboratory per week.',
                'empty_data' => '',
            ])
            ->add('instructors', TextareaType::class, [
                'required' => false,
                'label' => 'Instructors',
                'help' => 'Enter one instructor per line.',
                'empty_data' => '',
            ])
            ->add('textbooks', TextareaType::class, [
                'required' => false,
                'label' => 'Textbooks and other required materials',
                'help' => 'Enter one item per line.',
                'empty_data' => '',
            ])
            ->add('creditCategorization', TextType::class, [
                'required' => false,
                'label' => 'Credit categorization',
                'empty_data' => '',
            ])
            ->add('catalogDescription', TextareaType::class, [
                'required' => false,
                'label' => 'Catalog description',
                'empty_data' => '',
            ])
            ->add('prerequisites', TextareaType::class, [
                'required' => false,
                'label' => 'Prerequisites and co-requisites',
                'empty_data' => '',
            ])
            ->add('courseType', ChoiceType::class, [
                'required' => false,
                'label' => 'Course type',
                'placeholder' => 'Select a course type',
                'choices' => [
                    'Required' => 'R',
                    'Elective' => 'E',
                    'Selected elective' => 'SE',
                ],
            ])
            ->add('specificGoals', TextareaType::class, [
                'required' => false,
                'label' => 'Specific course goals',
                'help' => 'Enter one goal per line.',
                'empty_data' => '',
            ])
            ->add('courseOutcomes', TextareaType::class, [
                'required' => false,
                'label' => 'Course outcomes',
                'help' => 'Enter one outcome per line.',
                'empty_data' => '',
            ])
            ->add('studentOutcomes', TextareaType::class, [
                'required' => false,
                'label' => 'ABET student outcomes addressed',
                'help' => 'Enter one student outcome per line.',
                'empty_data' => '',
            ])
            ->add('topicsCovered', TextareaType::class, [
                'required' => false,
                'label' => 'Topics covered',
                'help' => 'Enter one topic per line.',
                'empty_data' => '',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CoordinatorTemplateData::class,
            'include_course_identity' => false,
            'include_offering_identity' => false,
        ]);
        $resolver->setAllowedTypes('include_course_identity', 'bool');
        $resolver->setAllowedTypes('include_offering_identity', 'bool');
    }
}
