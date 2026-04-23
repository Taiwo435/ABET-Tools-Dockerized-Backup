<?php
namespace App\Entity\Task;

/**
 * @see https://symfony.com/doc/current/forms.html#usage
 * to understand why
 */
class AccessTokenForm
{
    protected string $token;


    public function getToken(): string
    {
        return $this->token;
    }

    public function setToken(string $token): void
    {
        $this->token = $token;
    }
}