<?php

namespace App\Controller;

use App\Entity\Permissions;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class AccountController extends AbstractController
{
    /**
     * Permissions a user is allowed to self-request. Requesting ROLE_ADMIN
     * still requires an existing admin to approve it via the queue (see
     * AdminController::approve()) — this list only controls what shows up
     * as a checkbox on the request form, not who ends up granting it.
     */
    public const REQUESTABLE_PERMISSIONS = [
        Permissions::ROLE_ASSIGNMENTS_GRADES,
        Permissions::ROLE_CANVAS_FORMATTING,
        Permissions::ROLE_REPORTGEN,
        Permissions::ROLE_FACULTY_FORM,
        Permissions::ROLE_COORDINATOR_FORM,
        Permissions::ROLE_ADMIN,
    ];

    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/account/overview/', name: 'app_account_overview', methods: ['GET'])]
    public function me(#[CurrentUser] User $user): Response
    {
        return $this->render('account/me.html.twig', [
            'profile' => [
                'email' => $user->getEmail(),
                'asurite' => $user->getAsurite(),
                'status' => $user->isActive() ? 'Active' : 'Inactive',
                'permissions' => $user->getRoles(),
                'lastLogin' => $user->getLastLogin(),
                'createdAt' => $user->getCreatedAt(),
            ],
        ]);
    }

    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/account/request-access', name: 'app_account_request_access', methods: ['GET', 'POST'])]
    public function requestAccess(Request $request, #[CurrentUser] User $user, EntityManagerInterface $em): Response
    {
        $submitted = false;

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('request_access', (string) $request->request->get('csrf_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }

            $selected = (array) $request->request->all('permissions');
            $bitmask = 0;
            foreach (self::REQUESTABLE_PERMISSIONS as $permission) {
                if (in_array($permission->name, $selected, true)) {
                    $bitmask |= $permission->value;
                }
            }

            $user->setRequestedPermissions($bitmask > 0 ? $bitmask : null);
            $em->flush();

            $submitted = $bitmask > 0;

            return $this->redirectToRoute('app_account_request_access', ['submitted' => $submitted ? 1 : 0]);
        }

        return $this->render('account/request_access.html.twig', [
            'requestablePermissions' => self::REQUESTABLE_PERMISSIONS,
            'currentPermissions' => $user->getRoles(),
            'pendingPermissions' => $user->getRequestedPermissionNames(),
            'submitted' => $request->query->getBoolean('submitted'),
        ]);
    }
}
