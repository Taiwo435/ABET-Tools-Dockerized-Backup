<?php

namespace App\Entity\SyllabusTemplate;

enum CompletenessStatus: string
{
    case Incomplete = 'incomplete';
    case Complete = 'complete';
}
