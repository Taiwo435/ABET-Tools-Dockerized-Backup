<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'programs')]
#[ORM\UniqueConstraint(name: 'unique_program', columns: ['program_name', 'program_code', 'program_year'])]
class Program
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'program_id')]
    private ?int $id = null;

    #[ORM\Column(name: 'program_name', length: 255)]
    private string $name;

    #[ORM\Column(name: 'program_code', length: 50)]
    private string $code;

    #[ORM\Column(name: 'program_year', length: 20)]
    private string $year;

    public function __construct(string $name, string $code, string $year)
    {
        $this->name = trim($name);
        $this->code = strtoupper(trim($code));
        $this->year = trim($year);

        if ($this->name === '' || $this->code === '' || $this->year === '') {
            throw new \InvalidArgumentException('Program name, code, and year are required.');
        }
    }

    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getCode(): string { return $this->code; }
    public function getYear(): string { return $this->year; }

    public function getInitials(): string
    {
        $words = preg_split('/[\s-]+/', $this->name, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return implode('', array_map(
            static fn (string $word): string => strtoupper(substr($word, 0, 1)),
            $words,
        ));
    }
}
