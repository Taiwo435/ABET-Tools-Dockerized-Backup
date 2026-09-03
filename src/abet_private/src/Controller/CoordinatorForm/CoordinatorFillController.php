<?php

namespace App\Controller\CoordinatorForm;

use App\Entity\User;
use App\Service\Forms\CoordinatorFormLoader;
use App\Service\Forms\CoordinatorFormSaver;
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

final class CoordinatorFillController extends AbstractController
{

    #[IsGranted("ROLE_COORDINATOR_FORM")]
    #[Route(
        '/tool/coordinator-form/edit/{page}', 
        name: 'app_coordinator_form_edit', 
        methods: ['GET'], 
        requirements: ['page' => Requirement::DIGITS])]
    public function editForm(
        Request $request,
        #[CurrentUser] User $user,
        CoordinatorFormLoader $loader,
        FormFunctions $helper,
        int $page
    ) {

        ////////////////////////////////////////////////////
        // IN TEMPLATE 
        ////////////////////////////////////////////////////


        $pageNumber = $page;
        $formName = "coordinator-form";

        //print_r(getAllPageNames($formName));

        // Check if the page number is a valid integer
        if (filter_var($pageNumber, FILTER_VALIDATE_INT) === false) {
            header('Location: /coordinator-form/edit?page=1');
        }

        $pageNumber = (int) $pageNumber;

        // If the form is past the last page, it will redirect to the review page
        // if ($pageNumber > getPageCount($formName)) {
        //     header('Location: /coordinator-form/review');
        //     die();
        // }

        // Check if the page number is within the page count range
        if ($pageNumber < 1) {
            header('Location: /coordinator-form/edit?page=1');
            die();
        }

        // Loads the name of the JSON file corresponding to its page order in the index.json file
        $pageName = $helper->getPageNameFromNumber($formName, $pageNumber);

        $form = $helper->loadFormPage($formName, $pageName);


        $old = []; // The JSON data that will be autofilled onto the elements
        $backendErrorMessage = '';

        $session = $request->getSession();

        if (!empty($session->get('coordinator_form_error_flag')) && $session->get('coordinator_form_error_flag') === true) {
            $session->set('coordinator_form_error_flag', false);
            $old = $session->get('coordinator_form_old');
            $backendErrorMessage = $session->get('coordinator_form_error_message');
        } else {
            $loadedData = $loader->loadFormData($pageName);
            $old = is_array($loadedData) ? $loadedData : [];
        }


        ////////////////////////////////////////////////////
        // FIN
        ////////////////////////////////////////////////////

        return $this->render('forms/form.fillout.twig',[
            'form' => $form,
            'formName' => $formName,
            'homeRoute' => 'app_coordinator_form',
            'backendErrorMessage' => $backendErrorMessage,
            'old' => $old,
            'page' => $page,
        ]);
    }

    #[IsGranted("ROLE_COORDINATOR_FORM")]
    #[Route('/tool/coordinator-form/edit/submit.php', 
    name: 'app_coordinator_form_submit', 
    requirements: ['page' => Requirement::DIGITS],
    methods: ['POST'],
    )]
    public function submitForm(
        #[CurrentUser] User $user,
        CoordinatorFormSaver $saver,
        FormFunctions $helper,
        Request $request,
    )
    {
        $saver->handle_save();
        return $this->redirectToRoute('app_coordinator_form_edit', [
            'page' => $request->request->get('next_page_number'),
        ]);
    }
}