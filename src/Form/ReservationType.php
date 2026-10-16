<?php

namespace App\Form;

use App\Entity\Participation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Range;

final class ReservationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nbPlaces', IntegerType::class, [
                'label' => 'Nombre de places',
                'constraints' => [
                    new Range(
                        min: 1,
                        max: $options['places_restantes'],
                        notInRangeMessage: 'Vous pouvez réserver entre {{ min }} et {{ max }} place(s).'
                    ),
                ],
                'attr' => ['min' => 1, 'max' => $options['places_restantes']],
            ])
            ->add('submit', SubmitType::class, ['label' => 'Demander à participer']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Participation::class,
        ]);
        $resolver->setRequired('places_restantes');
        $resolver->setAllowedTypes('places_restantes', 'int');
    }
}
