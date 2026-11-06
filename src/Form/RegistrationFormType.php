<?php

namespace App\Form;

use App\Entity\Membre;
use App\Entity\Site;
use App\Enum\StatutMembre;
use App\Repository\SiteRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

final class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('prenom', TextType::class, ['label' => 'Prénom'])
            ->add('nom', TextType::class, ['label' => 'Nom'])
            ->add('email', EmailType::class, ['label' => 'Adresse email (@campus-eni.fr)'])
            ->add('statut', EnumType::class, [
                'class' => StatutMembre::class,
                'choice_label' => static fn (StatutMembre $statut): string => $statut->libelle(),
                'label' => 'Statut',
                'placeholder' => 'Choisir un statut',
            ])
            ->add('campusRattachement', EntityType::class, [
                'class' => Site::class,
                // choice_value explicite : un proxy lazy non initialisé rendrait value="".
                'choice_value' => 'id',
                'choice_label' => 'nom',
                'query_builder' => static fn (SiteRepository $repository) => $repository
                    ->createQueryBuilder('s')
                    ->where('s.actif = true')
                    ->orderBy('s.nom', 'ASC'),
                'label' => 'Campus de rattachement',
                'placeholder' => 'Choisir un campus',
            ])
            ->add('telephone', TelType::class, [
                'label' => 'Téléphone (facultatif)',
                'required' => false,
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'first_options' => ['label' => 'Mot de passe'],
                'second_options' => ['label' => 'Répéter le mot de passe'],
                'invalid_message' => 'Les deux mots de passe doivent être identiques.',
                'constraints' => [
                    new NotBlank(message: 'Le mot de passe est obligatoire.'),
                    new Length(
                        min: 8,
                        max: 64,
                        minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.'
                    ),
                ],
            ])
            ->add('submit', SubmitType::class, ['label' => 'Créer mon compte'])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Membre::class,
        ]);
    }
}
