<?php
namespace App\Entity\Task;

/**
 * @see https://symfony.com/doc/current/forms.html#usage
 * to see why i made this
 */
class ExtractionTokenForm
{
    protected string $term;
    protected string $department;


    public function getDepartment(): string
    {
        return $this->department;
    }

    public function setDepartment(string $department): void
    {
        $this->department = $department;
    }

    public function getTerm(): string
    {
        return $this->term;
    }

    public function setTerm(string $term): void
    {
        $this->term = $term;
    }

}