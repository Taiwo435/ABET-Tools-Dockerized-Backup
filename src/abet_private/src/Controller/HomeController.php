<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/test.php', name: 'app_homepage')]
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
}
