<?php

use Entity\Permissions;

require_once(__DIR__."/../../vendor/autoload.php");
require_once(__DIR__."/../../bootstrap.php");

use Entity\User;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;

// $dotenv = Dotenv\Dotenv::createImmutable(__DIR__."/../../../docker");
// $dotenv->load();

/**
 * PHP stuff
 * @return ?User nullable, the user if they exist
 */
function getUserByEmail(EntityManager $em, string $email)
{
    // Use the repository method findOneBy to get a user by their email
    $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);

    return $user;
}


test('User synchronization with DB Example', function () {
    $entityManager = Services::getEntityManager();

    $email = "test@doctrine.orm";
    $user = getUserByEmail($entityManager, $email);

    // $user = new User();
    // $user->setEmail('test@doctrine.orm');

    // add test user if not already there
    if ($user === null) {
        $pass = "RAHHHHH"; // i know its saved in the hash column, very scuffed i know
        $role = "faculty";
        $is_active = false;

        $user = new User();
        $user->setEmail($email);
        $user->setPasswordHash($pass);
        $user->setRole($role);
        $user->setIsActive($is_active);
        $entityManager->persist($user);
        $entityManager->flush();
    }

    $user = getUserByEmail($entityManager, $email);

    echo $user->getEmail();
    expect($user->getEmail() === $email)->toBeTrue();

});

test("User Permissions Interface Example", function () {
    $entityManager = Services::getEntityManager();

    $email = "test@doctrine.orm";
    $user = getUserByEmail($entityManager, $email);

    if ($user === null) {
        $user = new User();
    }

    $user->setPermission(Permissions::AdminPanel, true);

    $perm = $user->hasPermission(Permissions::AdminPanel);
    expect($perm)->toBeTrue();

    $user->setPermission(Permissions::AdminPanel, false);

    $perm = $user->hasPermission(Permissions::AdminPanel);
    expect($perm)->toBeFalse(); 
});

// it is the same as test
it("checks if all Permissions have unique values, just in case.", function () {
    // unique values
    $values = array_column(Permissions::cases(), 'value');
    expect(count($values) === count(array_unique($values)))->toBe(true);
});