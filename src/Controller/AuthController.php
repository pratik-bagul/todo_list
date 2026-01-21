<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AuthController extends AbstractController
{
    #[Route('/register', name: 'auth_register', methods: ['GET','POST'])]
    public function register(Request $request, EntityManagerInterface $em): Response
    {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // 1) get plain password from the form (unmapped field)
            $plain = (string) $form->get('plainPassword')->getData();

            // 2) hash it (native password_hash is fine for your setup)
            $hash = password_hash($plain, PASSWORD_DEFAULT);

            // 3) set hashed password and persist
            $user->setPassword($hash);
            $em->persist($user);
            $em->flush();

            $this->addFlash('success', 'Registration successful. Please log in.');
            return $this->redirectToRoute('auth_login');
        }

        return $this->render('auth/register.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/login', name: 'auth_login', methods: ['GET','POST'])]
    public function login(Request $request, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST')) {
            $username = trim((string) $request->request->get('username'));
            $password = (string) $request->request->get('password');

            $user = $em->getRepository(User::class)->findOneBy(['username' => $username]);

            if (!$user || !password_verify($password, $user->getPassword())) {
                $this->addFlash('error', 'Invalid credentials.');
                return $this->redirectToRoute('auth_login');
            }

            // Store minimal session data
            $session = $request->getSession();
            $session->set('user_id', $user->getId());
            $session->set('username', $user->getUsername());

            $this->addFlash('success', 'Logged in.');
            return $this->redirectToRoute('task_index');
        }

        // GET: show login form
        return $this->render('auth/login.html.twig');
    }

    #[Route('/logout', name: 'auth_logout', methods: ['POST','GET'])]
    public function logout(Request $request): Response
    {
        $request->getSession()->clear();
        $this->addFlash('info', 'Logged out.');
        return $this->redirectToRoute('auth_login');
    }
}
