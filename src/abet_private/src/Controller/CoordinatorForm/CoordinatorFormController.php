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

final class CoordinatorFormController extends AbstractController
{
    // public function __construct(
    //     private Connection $connection,
    // ) {
    // }


    #[Route('/tool/coordinator-form', name: 'app_coordinator_form', methods: ['GET'])]
    public function getForm(
        #[CurrentUser] User $user,
        CoordinatorFormLoader $loader,
        FormFunctions $helper,
    ) {
    ////////////////////////////////////////////////////
    // INDEX PHP
    ////////////////////////////////////////////////////
    
    $formName = "coordinator-form";
    // $formDisplayTitle = "Coordinator Form";
    $pageTitle = "Coordinator Form";
    $formBasePath = "/coordinator-form";
    $formCssPath = "/assets/css/faculty-form.css";

    $completeMessage = "The form is complete. If necessary, you can edit your responses. Otherwise, you are done with this form and can safely navigate away from this page.";
    $incompleteMessage = "Your form is not yet complete. Click \"Start / Continue\" or select a page to fill out the remaining sections.";

    ////////////////////////////////////////////////////
    // AFTER INDEX PHP
    // BEFORE TEMPLATE COMPUTATION
    ////////////////////////////////////////////////////
    $sections = [];
    $totalForms = 0;
    $totalCompleted = 0;

    $pageNames = $helper->getAllPageNames($formName);

    foreach ($pageNames as $i => $pageName) {
        $form = $helper->loadFormPage($formName, $pageName);
        $title = $form["title"] ?? $pageName;

        $fields = $loader->normalizeFields($form);
        $saved = $loader->loadValues($pageName);

        $reqCount = 0;
        $reqFilled = 0;
        $anyFilled = false;

        foreach ($fields as $f) {
            $fname = $f["name"];
            $type = $f["type"];
            $val = $saved[$fname] ?? null;

            if ($type === "expandable-grid") {
                if (count($loader->decodeGridRows($val)) > 0) $anyFilled = true;
            } else {
                if (!$loader->isEmptyValue($val)) $anyFilled = true;
            }

            if ($f["required"]) {
                $reqCount++;
                if ($type === "expandable-grid") {
                    if (count($loader->decodeGridRows($val)) > 0) $reqFilled++;
                } else {
                    if (!$loader->isEmptyValue($val)) $reqFilled++;
                }
            }
        }

        if ($reqCount === 0) {
            $status = $anyFilled ? "Completed" : "Not Started";
            $percent = $anyFilled ? 100 : 0;
        } else if ($reqFilled >= $reqCount) {
            $status = "Completed";
            $percent = (int)floor(($reqFilled / $reqCount) * 100);
        } else if ($anyFilled || $reqFilled > 0) {
            $status = "In Progress";
            $percent = (int)floor(($reqFilled / $reqCount) * 100);
        } else {
            $status = "Not Started";
            $percent = 0;
        }

        $totalForms++;
        if ($anyFilled) {
            $totalCompleted++;
        }

        $sections[] = [
            "pageNumber" => $i + 1,
            "name" => $pageName,
            "title" => $title,
            "status" => $status,
            "percent" => $percent,
            "requiredCount" => $reqCount,
            "requiredFilled" => $reqFilled,
            ];
        }

        $overallPercent = ($totalForms > 0) ? (int)floor(($totalCompleted / $totalForms) * 100) : 0;

        ////////////////////////////////////////////////////
        // IN TEMPLATE 
        ////////////////////////////////////////////////////

        ////////////////////////////////////////////////////
        // FIN
        ////////////////////////////////////////////////////

        return $this->render('forms/form.select.twig',[
            'formName' => 'coordinator-form',
            'formDisplayTitle' => 'Coordinator Form',
            'pageTitle' => 'Coordinator Form',
            'formBasePath' => '/coordinator-form',
            'formCssPath' => '/assets/css/faculty-form.css',
            'completeMessage' => 'The form is complete. If necessary, you can edit your responses. Otherwise, you are done with this form and can safely navigate away from this page.',
            'incompleteMessage' => 'Your form is not yet complete. Click "Start / Continue" or select a page to fill out the remaining sections.',

            "overallPercent" => $overallPercent

        ]);
    }
}