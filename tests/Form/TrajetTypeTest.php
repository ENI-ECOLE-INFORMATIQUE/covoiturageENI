<?php

namespace App\Tests\Form;

use App\Entity\Membre;
use App\Entity\Trajet;
use App\Form\TrajetType;
use App\Tests\DatabasePrimerTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class TrajetTypeTest extends KernelTestCase
{
    use DatabasePrimerTrait;

    /**
     * Régression : quand le campus du conducteur est déjà dans la map d'identité
     * sous forme de proxy lazy non initialisé (via Membre::campusRattachement),
     * l'option correspondante du select doit quand même avoir une value non vide.
     * Sinon le navigateur soumet "" et le trajet part avec site_id null.
     */
    public function testToutesLesOptionsSiteOntUneValeurNonVide(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        self::preparerBase($container);

        $em = $container->get('doctrine')->getManager();
        // Vider la map d'identité : au rechargement, le campus de rattachement
        // du membre n'est qu'un proxy lazy non initialisé.
        $em->clear();
        $conducteur = $em->getRepository(Membre::class)->findOneBy(['email' => 'camille.legoff@campus-eni.fr']);
        $this->assertNotNull($conducteur);

        $form = $container->get('form.factory')->create(TrajetType::class, new Trajet(), ['conducteur' => $conducteur]);
        $view = $form->createView();

        $this->assertNotEmpty($view['site']->vars['choices']);
        foreach ($view['site']->vars['choices'] as $choice) {
            $this->assertNotSame(
                '',
                $choice->value,
                \sprintf('L\'option "%s" a une value vide (proxy lazy non initialisé).', $choice->label)
            );
        }
    }
}
