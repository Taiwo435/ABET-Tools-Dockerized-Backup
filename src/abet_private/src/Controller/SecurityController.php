<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;


// $response = new RedirectResponse('http://example.com/');

/**
 * Controller that handles the routes for security-related paths 
 * 
 * /login does not take care of login, it is handed off to FormLoginAuthenticator
 * @see https://symfony.com/doc/7.4/security.html#form-login
 * this describes everything about the form login
 */
class SecurityController extends AbstractController
{
    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {

        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();

        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        require_once getenv('ABET_PRIVATE_DIR') . '/lib/clerk.php';

        $clerkPublishableKey = null;
        $clerkFrontendApi = null;

        try {
            $key = trim((string) getenv('CLERK_PUBLISHABLE_KEY'));
            if ($key !== '' && preg_match('/^pk_(test|live)_[A-Za-z0-9_-]+$/', $key)) {
                $clerkPublishableKey = $key;
                $clerkFrontendApi = clerk_frontend_api_domain();
                clerk_browser_csp();
            }
        } catch (\Throwable) {
            // Clerk misconfigured — degrade to password-only login.
            $clerkPublishableKey = null;
            $clerkFrontendApi = null;
        }

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
            'clerkPublishableKey' => $clerkPublishableKey,
            'clerkFrontendApi' => $clerkFrontendApi,
        ]);
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}
