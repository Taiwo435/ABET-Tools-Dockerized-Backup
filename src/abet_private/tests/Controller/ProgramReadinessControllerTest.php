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
        
        // Check IsGranted attribute on class
        $classGrantAttributes = $reflection->getAttributes(IsGranted::class);
        self::assertCount(1, $classGrantAttributes);
        self::assertSame('IS_AUTHENTICATED_FULLY', $classGrantAttributes[0]->newInstance()->attribute);

        // Check Route attribute on index method
        $method = $reflection->getMethod('index');
        $routeAttributes = $method->getAttributes(Route::class);
        self::assertCount(1, $routeAttributes);

        $route = $routeAttributes[0]->newInstance();
        self::assertSame('/program/{programId}/readiness', $route->path);
        self::assertSame('app_program_readiness', $route->name);
        self::assertSame(['GET'], $route->methods);
    }

    public function testAnonymousRequestIsRedirected(): void
    {
        $client = static::createClient();
        $client->request('GET', '/program/9999/readiness');

        // Since IS_AUTHENTICATED_FULLY is required, anonymous user is redirected to login path
        self::assertResponseRedirects('/login2');
    }
}
