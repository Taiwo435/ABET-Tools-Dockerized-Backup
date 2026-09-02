<?php

namespace App\Controller\FacultyForm;

use App\Entity\User;
use App\Service\Forms\FacultyFormLoader;
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


    #[Route('/tool/faculty-form/review', 
    name: 'app_faculty_form_review', 
    methods: ['GET'], 
    )]
    public function getForm(
        #[CurrentUser] User $user,
        FacultyFormLoader $loader,
        FormFunctions $helper,
    ) {

        $formName = "faculty-form";
        $formBasePath = "/tool/faculty-form";
        $reviewTitle = "Faculty Form Review";
        $reviewCssPath = "/assets/css/faculty-form-review.css";
        $homeRoute = "app_faculty_form";


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
            'homeRoute' => $homeRoute,
            'loadValues' => fn (string $pageName) => $loader->loadFormData($pageName),
        ]);
    }
}