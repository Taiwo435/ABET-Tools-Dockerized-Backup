<?php
namespace App\Entity\Task\AssignmentsGrades;

/**
 * @see https://symfony.com/doc/current/forms.html#usage
 * to see why i made this
 */
class NewExtractionForm
{
    protected string $term;
    protected string $degree;


    public function getDegree(): string
    {
        return $this->degree;
    }

    public function setDegree(string $degree): void
    {
        $this->degree = $degree;
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