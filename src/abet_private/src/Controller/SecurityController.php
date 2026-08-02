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
    #[Route(path: '/login', name: 'app_login')] //Issue #132: Migrated endpoint from testing path '/login2' to active production path
    public function login(AuthenticationUtils $authenticationUtils): Response
    {

        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();

        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route(path: '/logout', name: 'app_logout')] //Issue #132: Migrated endpoint from testing path '/logout2' to active production path
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
    // #132: /auth/login.php and /auth/logout.php are legacy raw paths that
    // used to serve duplicate, dead-code copies of this same functionality.
    // Redirecting instead of deleting, in case anything still links to the
    // old raw path directly.
    #[Route(path: '/auth/login.php', name: 'app_legacy_login_redirect')]
    public function legacyLoginRedirect(): Response
    {
        return $this->redirectToRoute('app_login');
    }

    #[Route(path: '/auth/logout.php', name: 'app_legacy_logout_redirect')]
    public function legacyLogoutRedirect(): Response
    {
        return $this->redirectToRoute('app_logout');
    }

}
