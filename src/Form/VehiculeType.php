<?php

namespace App\Form;

use App\Entity\Vehicule;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Positive;

final class VehiculeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('marque', TextType::class, ['label' => 'Marque'])
            ->add('modele', TextType::class, ['label' => 'Modèle'])
            ->add('couleur', TextType::class, ['label' => 'Couleur (facultatif)', 'required' => false])
            ->add('plaque', TextType::class, ['label' => 'Plaque d\'immatriculation'])
            ->add('nbPlaces', IntegerType::class, [
                'label' => 'Nombre de places passagers',
                'constraints' => [new Positive(message: 'Le véhicule doit avoir au moins une place.')],
                'attr' => ['min' => 1, 'max' => 8],
            ])
            ->add('fumeur', CheckboxType::class, [
                'label' => 'Fumeur',
                'required' => false,
            ])
            ->add('animaux', CheckboxType::class, [
                'label' => 'Animaux acceptés',
                'required' => false,
            ])
            ->add('submit', SubmitType::class, ['label' => 'Enregistrer le véhicule']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Vehicule::class,
        ]);
    }
}
