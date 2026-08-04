<?php

declare(strict_types=1);

namespace Tests\Controller;

use App\Controller\ProgramReadinessController;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use ReflectionClass;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class ProgramReadinessControllerTest extends WebTestCase
{
    public function testRouteAttributes(): void
    {
        self::assertTrue(
            class_exists(ProgramReadinessController::class),
            'ProgramReadinessController does not exist.'
        );

        $reflection = new ReflectionClass(ProgramReadinessController::class);
        
        $classGrantAttributes = $reflection->getAttributes(IsGranted::class);
        self::assertCount(1, $classGrantAttributes);
        self::assertSame('IS_AUTHENTICATED_FULLY', $classGrantAttributes[0]->newInstance()->attribute);

        $method = $reflection->getMethod('program');
        $routeAttributes = $method->getAttributes(Route::class);
        self::assertCount(1, $routeAttributes);

        $route = $routeAttributes[0]->newInstance();
        self::assertSame('/program/{programId}/readiness', $route->path);
        self::assertSame('app_program_readiness', $route->name);
        self::assertSame(['programId' => '\d+'], $route->requirements);
        self::assertSame(['GET'], $route->methods);
    }

    public function testLegacyRoutesRedirectIntoTheCoordinatorWorkspace(): void
    {
        $source = file_get_contents((new ReflectionClass(ProgramReadinessController::class))->getFileName());

        self::assertIsString($source);
        self::assertStringContainsString("redirectToRoute('app_admin_syllabus_templates'", $source);
        self::assertStringContainsString("'ready' => 'appendix_a'", $source);
        self::assertStringContainsString("'awaiting review' => 'offerings'", $source);
        self::assertStringContainsString("? 'offerings'", $source);
        self::assertStringNotContainsString("render('tools/program_readiness/index.html.twig'", $source);
    }

    public function testProgramSelectionRouteAttributes(): void
    {
        $reflection = new ReflectionClass(ProgramReadinessController::class);
        $routeAttributes = $reflection->getMethod('selectProgram')->getAttributes(Route::class);

        self::assertCount(1, $routeAttributes);
        $route = $routeAttributes[0]->newInstance();
        self::assertSame('/program/readiness', $route->path);
        self::assertSame('app_program_readiness_select', $route->name);
        self::assertSame(['GET'], $route->methods);
    }

    public function testAnonymousRequestIsRedirected(): void
    {
        $client = static::createClient();
        $client->request('GET', '/program/9999/readiness');

        self::assertResponseRedirects('/login');
    }
}
