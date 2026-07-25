<?php

namespace Tests\Controller;

use App\Entity\User;
use App\Entity\Permissions;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HomepageControllerTest extends WebTestCase
{
    public function testIndex(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);

        $email = 'test-homepage@example.com';
        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($user === null) {
            $user = new User();
            $user->setEmail($email);
            $user->setPasswordHash('password');
            $user->setIsActive(true);
            $user->setPermission(Permissions::ROLE_ADMIN, true);
            $em->persist($user);
            $em->flush();
        }

        $client->loginUser($user);
        $client->request('GET', '/home2');

        self::assertResponseIsSuccessful();

        // Clean up
        $em->remove($user);
        $em->flush();
    }
}
