<?php

namespace App\Controller\FacultyForm;

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

final class FacultyReviewController extends AbstractController
{
    // public function __construct(
    //     private Connection $connection,
    // ) {
    // }


    #[Route('/tool/coordinator-form/review', 
    name: 'app_coordinator_form_review', 
    methods: ['GET'], 
    )]
    public function getForm(
        #[CurrentUser] User $user,
        CoordinatorFormLoader $loader,
        FormFunctions $helper,
    ) {

        $formName = "coordinator-form";
        $formBasePath = "/tool/coordinator-form";
        $reviewTitle = "Coordinator Form Review";
        $reviewCssPath = "/assets/css/faculty-form-review.css";

        ////////////////////////////////////////////////////
        // IN TEMPLATE 
        ////////////////////////////////////////////////////

        $pageNames = $helper->getAllPageNames($formName);


        ////////////////////////////////////////////////////
        // FIN
        ////////////////////////////////////////////////////

        return $this->render('forms/form.review.twig',[
            'formName' => $formName,
            'formBasePath' => $formBasePath,
            'reviewTitle' => $reviewTitle,
            'reviewCssPath' => $reviewCssPath,
            'pageNames' => $pageNames,
            'loadValues' => fn (string $pageName) => $loader->loadValues($pageName),
        ]);
    }
}