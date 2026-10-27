<?php

namespace App\Controller;

use App\Entity\Membre;
use App\Form\RegistrationFormType;
use App\Repository\MembreRepository;
use App\Security\EmailVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

final class SecurityController extends AbstractController
{
    public function __construct(
        private readonly EmailVerifier $emailVerifier,
    ) {
    }

    #[Route('/connexion', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        return $this->render('security/login.html.twig', [
            'last_email' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route('/deconnexion', name: 'app_logout')]
    public function logout(): never
    {
        throw new \LogicException('Cette méthode peut rester vide : elle est interceptée par le pare-feu.');
    }

    #[Route('/inscription', name: 'app_register')]
    public function register(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $entityManager): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $membre = new Membre();
        $form = $this->createForm(RegistrationFormType::class, $membre);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $membre->setMotDePasse(
                $passwordHasher->hashPassword($membre, $form->get('plainPassword')->getData())
            );
            $membre->setRoles(['ROLE_USER']);

            $entityManager->persist($membre);
            $entityManager->flush();

            $this->emailVerifier->sendEmailConfirmation(
                'app_verify_email',
                $membre,
                (new TemplatedEmail())
                    ->from(new Address('no-reply@campus-eni.fr', 'ENI Covoiturage'))
                    ->to($membre->getEmail())
                    ->subject('ENI Covoiturage : confirmez votre adresse email')
                    ->htmlTemplate('security/confirmation_email.html.twig')
            );

            return $this->redirectToRoute('app_attente_verification');
        }

        return $this->render('security/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }

    #[Route('/attente-verification', name: 'app_attente_verification')]
    public function attenteVerification(): Response
    {
        return $this->render('security/attente_verification.html.twig');
    }

    #[Route('/verification-email', name: 'app_verify_email')]
    public function verifierEmail(Request $request, MembreRepository $membreRepository, EntityManagerInterface $entityManager): Response
    {
        $membre = $membreRepository->find($request->query->getInt('id'));

        if (null === $membre) {
            throw $this->createNotFoundException('Lien de vérification invalide.');
        }

        try {
            $this->emailVerifier->handleEmailConfirmation($membre, $request);
        } catch (VerifyEmailExceptionInterface) {
            $this->addFlash('danger', 'Le lien de vérification est invalide ou a expiré.');

            return $this->redirectToRoute('app_login');
        }

        if (null === $membre->getEmailVerifieLe()) {
            $membre->setEmailVerifieLe(new \DateTimeImmutable());
            $entityManager->flush();
        }

        $this->addFlash('success', 'Votre adresse email est vérifiée. Vous pouvez maintenant vous connecter.');

        return $this->redirectToRoute('app_login');
    }
}
