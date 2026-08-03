<?php

namespace App\Entity\SyllabusTemplate;

enum RevisionAuthorType: string
{
    case Faculty = 'faculty';
    case Coordinator = 'coordinator';
}
