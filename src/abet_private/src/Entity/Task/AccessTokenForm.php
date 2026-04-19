<?php
namespace App\Entity\Task;

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