<?php

require_once(__DIR__."/../../vendor/autoload.php");

use Entity\User;
use Doctrine\ORM\Tools\Setup;
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

test('User Save to database', function () {
    require_once(__DIR__."/../../bootstrap.php");

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
