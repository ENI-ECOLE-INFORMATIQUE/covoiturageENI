<?php

namespace App\Form;

use App\Entity\Site;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class SiteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, ['label' => 'Nom du campus'])
            ->add('adresse', TextType::class, ['label' => 'Adresse (facultatif)', 'required' => false])
            ->add('ville', TextType::class, ['label' => 'Ville (facultatif)', 'required' => false])
            ->add('codePostal', TextType::class, ['label' => 'Code postal (facultatif)', 'required' => false])
            ->add('submit', SubmitType::class, ['label' => 'Enregistrer le campus']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Site::class,
        ]);
    }
}
