<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Entity\User;
use App\Entity\Permissions;

final class HomeController extends AbstractController
{
    // route where i can test some bs
    #[Route('/test.php', name: 'app_test')]
    public function index(): Response
    {
        ob_clean();
        include getenv("ABET_PUBLIC_DIR") . '/auth/register.php';
        $content = ob_get_clean();

        return new Response($content);
        // return $this->render('homepage/index.html.twig', [
        //     'controller_name' => 'HomepageController',
        // ]);
    }
    
    
    // the base template
    // #[IsGranted('IS_AUTHENTICATED_FULLY')]
    // #[Route('/base', name: 'base')]
    // public function base(#[CurrentUser] User $user) {
    //     $parts = explode('@', (string)$user->getEmail());
    //     $asurite = $parts[0] ?? 'user';
    //     return $this->render('base.html.twig', 
    //         ['user'=> $user,
    //         'asurite'=> $asurite]);
    // }

    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/home', name: 'app_homepage')]
    public function home(#[CurrentUser] User $user) {
        $missingPermissions = array_filter(
            AccountController::REQUESTABLE_PERMISSIONS,
            static fn (Permissions $permission): bool => !$user->hasPermission($permission),
        );

        return $this->render('homepage/home.html.twig', [
            'hasMissingPermissions' => count($missingPermissions) > 0,
            'pendingPermissions' => $user->getRequestedPermissionNames(),
        ]);
    }

    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/header', name: 'app_header')]
    public function header(#[CurrentUser] User $user) {
        $parts = explode('@', (string)$user->getEmail());
        $asurite = $parts[0] ?? 'user';
        return $this->render('shared/header.html.twig', [
        ]);
    }
}
