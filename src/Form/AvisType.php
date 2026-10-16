<?php

namespace App\Form;

use App\Entity\Avis;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

final class AvisType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('note', ChoiceType::class, [
                'label' => 'Note',
                'choices' => [
                    '5 — Excellent' => 5,
                    '4 — Très bien' => 4,
                    '3 — Bien' => 3,
                    '2 — Moyen' => 2,
                    '1 — Décevant' => 1,
                ],
                'expanded' => true,
                'multiple' => false,
                'constraints' => [new NotBlank(message: 'La note est obligatoire.')],
            ])
            ->add('commentaire', TextareaType::class, [
                'label' => 'Commentaire (facultatif)',
                'required' => false,
            ])
            ->add('submit', SubmitType::class, ['label' => 'Publier mon avis']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Avis::class,
        ]);
    }
}
