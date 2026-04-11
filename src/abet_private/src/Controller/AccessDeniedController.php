<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Handles routes that have to do with errors. 
 */
final class AccessDeniedController extends AbstractController
{

    #[Route('/403', name: 'app_access_denied')]
    public function access_denied(): Response
    {
        return $this->render('access_denied/index.html.twig', [
            'controller_name' => 'AccessDeniedController',
            'error_name' => '403 Access Denied',
        ]);
    }

    #[Route('/404', name: 'app_not_found')]
    public function not_found(): Response
    {
        return $this->render('access_denied/index.html.twig', [
            'controller_name' => 'AccessDeniedController',
            'error_name' => '404 Not Found',
        ]);
    }

}
