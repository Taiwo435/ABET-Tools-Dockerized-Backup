<?php

namespace App\Controller\CoordinatorForm;

use App\Entity\User;
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
    ) {
        return $this->render('forms/form.select.twig');
    }
}