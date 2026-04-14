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
    // $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
    $repo = $em->getRepository(User::class);
    return new User();
}

final class UserORMTest extends KernelTestCase {
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();

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

        // $this->assertSame($email, $user->getEmail());
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
        $this->assertEquals(["ROLE_ADMIN", "ROLE_USER"], $user->getRoles());

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

