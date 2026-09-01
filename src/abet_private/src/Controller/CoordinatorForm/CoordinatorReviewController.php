<?php

namespace App\Controller\CoordinatorForm;

use App\Entity\User;
use App\Service\Forms\CoordinatorFormLoader;
use App\Service\Forms\FormFunctions;
use Dba\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Routing\Requirement\Requirement;

final class CoordinatorReviewController extends AbstractController
{
    // public function __construct(
    //     private Connection $connection,
    // ) {
    // }


    #[Route('/tool/coordinator-form/review/{page}', name: 'app_coordinator_form_review', methods: ['GET'], requirements: ['page' => Requirement::DIGITS])]
    public function getForm(
        #[CurrentUser] User $user,
        CoordinatorFormLoader $loader,
        FormFunctions $helper,
        int $page
    ) {

        ////////////////////////////////////////////////////
        // IN TEMPLATE 
        ////////////////////////////////////////////////////

        ////////////////////////////////////////////////////
        // FIN
        ////////////////////////////////////////////////////

        return $this->render('forms/form.fillout.twig',[

        ]);
    }
}