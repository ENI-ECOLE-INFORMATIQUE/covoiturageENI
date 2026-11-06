<?php

namespace App\Form;

use App\Entity\Membre;
use App\Entity\Site;
use App\Entity\Trajet;
use App\Entity\Vehicule;
use App\Enum\SensTrajet;
use App\Repository\SiteRepository;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\PositiveOrZero;

final class TrajetType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Membre $conducteur */
        $conducteur = $options['conducteur'];

        $builder
            ->add('vehicule', EntityType::class, [
                'class' => Vehicule::class,
                // choice_value explicite : la valeur par défaut passe par un accessor
                // "raw" qui lit null sur un proxy lazy non initialisé (PHP 8.4).
                'choice_value' => 'id',
                'choice_label' => function (Vehicule $vehicule): string {
                    return \sprintf('%s %s (%d places)', $vehicule->getMarque(), $vehicule->getModele(), $vehicule->getNbPlaces());
                },
                'query_builder' => static fn (EntityRepository $repository) => $repository
                    ->createQueryBuilder('v')
                    ->where('v.membre = :membre')
                    ->setParameter('membre', $conducteur)
                    ->orderBy('v.marque', 'ASC'),
                'label' => 'Véhicule utilisé',
                'placeholder' => 'Choisir un véhicule',
                'constraints' => [new NotBlank(message: 'Le véhicule est obligatoire.')],
            ])
            ->add('site', EntityType::class, [
                'class' => Site::class,
                // choice_value explicite : sinon le campus déjà chargé comme proxy lazy
                // (ex. campus de rattachement du conducteur) rend une option value="".
                'choice_value' => 'id',
                'choice_label' => 'nom',
                'query_builder' => static fn (SiteRepository $repository) => $repository
                    ->createQueryBuilder('s')
                    ->where('s.actif = true')
                    ->orderBy('s.nom', 'ASC'),
                'label' => 'Campus concerné',
                'placeholder' => 'Choisir un campus',
            ])
            ->add('sens', EnumType::class, [
                'class' => SensTrajet::class,
                'choice_label' => static fn (SensTrajet $sens): string => $sens->libelle(),
                'label' => 'Sens du trajet',
                'expanded' => true,
                'multiple' => false,
            ])
            ->add('villeDepart', TextType::class, ['label' => 'Ville de départ'])
            ->add('villeArrivee', TextType::class, ['label' => 'Ville d\'arrivée'])
            ->add('adresseRendezVous', TextType::class, [
                'label' => 'Point de rendez-vous (facultatif)',
                'required' => false,
            ])
            ->add('dateDepart', DateTimeType::class, [
                'label' => 'Date et heure de départ',
                'widget' => 'single_text',
                'constraints' => [new NotBlank(message: 'La date de départ est obligatoire.')],
            ])
            ->add('dureeEstimee', IntegerType::class, [
                'label' => 'Durée estimée (minutes, facultatif)',
                'required' => false,
                'constraints' => [new Positive(message: 'La durée estimée doit être positive.')],
            ])
            ->add('prix', NumberType::class, [
                'label' => 'Participation aux frais (€)',
                'input' => 'string',
                'scale' => 2,
                'html5' => true,
                'attr' => ['step' => '0.5', 'min' => 0],
                'constraints' => [new PositiveOrZero(message: 'Le prix ne peut pas être négatif.')],
            ])
            ->add('commentaire', TextareaType::class, [
                'label' => 'Commentaire (facultatif)',
                'required' => false,
            ])
            ->add('submit', SubmitType::class, ['label' => 'Publier le trajet']);

        $builder->get('dateDepart')->addEventListener(
            FormEvents::POST_SUBMIT,
            static function (FormEvent $event): void {
                $date = $event->getData();
                if ($date instanceof \DateTimeInterface && $date <= new \DateTimeImmutable()) {
                    $event->getForm()->addError(new FormError('La date de départ doit être dans le futur.'));
                }
            }
        );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Trajet::class,
        ]);
        $resolver->setRequired('conducteur');
        $resolver->setAllowedTypes('conducteur', [Membre::class]);
    }
}
