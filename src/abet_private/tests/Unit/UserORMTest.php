<?php
declare(strict_types=1);
namespace Tests\Unit;

require_once(__DIR__."/../../vendor/autoload.php");
require_once(__DIR__."/../../bootstrap.php");

use PHPUnit\Framework\TestCase;

use App\Entity\User;
use App\Entity\Permissions;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Doctrine\ORM\EntityManagerInterface;

/**
 * PHP stuff
 * @return ?User nullable, the user if they exist
 */
function getUserByEmail(EntityManager $em, string $email)
{
    // Use the repository method findOneBy to get a user by their email
    $repo = $em->getRepository(User::class);
    $user = $repo->findOneBy(["email"=> $email]);
    return $user;
}

final class UserORMTest extends KernelTestCase {
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();

        // print_r($_ENV);

        $this->entityManager = static::getContainer()
            ->get(EntityManagerInterface::class);
    }

    public function testUserSynchronization(): void {

        $email = "test@doctrine.orm";
        $user = getUserByEmail($this->entityManager, $email);

        // $user = new User();
        // $user->setEmail('test@doctrine.orm');

        // add test user if not already there
        if ($user === null) {
            $pass = "RAHHHHH"; // i know its saved in the hash column, very scuffed i know
            $is_active = false;

            $user = new User();
            $user->setEmail($email);
            $user->setPasswordHash($pass);
            $user->setIsActive($is_active);
            $this->entityManager->persist($user);
            $this->entityManager->flush();
        }

        $user = getUserByEmail($this->entityManager, $email);
        $this->assertNotEquals(null, $user);

        $this->assertSame($email, $user->getEmail());
    }

    public function testUserPermissions() {
        $email = "test@doctrine.orm";
        $user = getUserByEmail($this->entityManager, $email);

        if ($user === null) {
            $user = new User();
        }

        $user->setPermission(Permissions::ROLE_ADMIN, true);

        $perm = $user->hasPermission(Permissions::ROLE_ADMIN);
        $this->assertTrue($perm, "User expected to have AdminPanel role");
        // getRoles() mirrors hasPermission()'s admin short-circuit: an admin
        // implicitly has every permission role, otherwise Symfony's
        // access_control/#[IsGranted] checks (which read getRoles(), not
        // hasPermission()) would deny an admin access to tools they don't
        // separately hold the specific permission bit for.
        $expectedRoles = array_map(fn ($p) => $p->name, Permissions::cases());
        $expectedRoles[] = 'ROLE_USER';
        $this->assertEqualsCanonicalizing($expectedRoles, $user->getRoles());

        $user->setPermission(Permissions::ROLE_ADMIN, false);

        $perm = $user->hasPermission(Permissions::ROLE_ADMIN);
        $this->assertFalse($perm, "User expected to not have AdminPanel role any longer");
        $this->assertEquals(["ROLE_USER"], $user->getRoles());
    }

    public function testPermissionUniqueness() {
        $values = array_column(Permissions::cases(), 'value');
        $this->assertTrue(count($values) === count(array_unique($values)), "Every permission should have distinct values");
    }
}

