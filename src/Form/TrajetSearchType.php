<?php

namespace App\Form;

use App\Entity\Site;
use App\Enum\SensTrajet;
use App\Repository\SiteRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Formulaire GET de recherche de trajets : les filtres sont facultatifs.
 */
final class TrajetSearchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('site', EntityType::class, [
                'class' => Site::class,
                'choice_label' => 'nom',
                'query_builder' => static fn (SiteRepository $repository) => $repository
                    ->createQueryBuilder('s')
                    ->where('s.actif = true')
                    ->orderBy('s.nom', 'ASC'),
                'label' => 'Campus',
                'required' => false,
                'placeholder' => 'Tous les campus',
            ])
            ->add('sens', ChoiceType::class, [
                'label' => 'Sens',
                'required' => false,
                'placeholder' => 'Tous les sens',
                'choices' => [
                    'Vers le campus' => SensTrajet::VersSite,
                    'Depuis le campus' => SensTrajet::DepuisSite,
                ],
            ])
            ->add('date', DateType::class, [
                'label' => 'Date',
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('ville', TextType::class, [
                'label' => 'Ville (facultatif)',
                'required' => false,
            ])
            ->add('submit', SubmitType::class, ['label' => 'Rechercher']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method' => 'GET',
            'csrf_protection' => false,
        ]);
    }
}
