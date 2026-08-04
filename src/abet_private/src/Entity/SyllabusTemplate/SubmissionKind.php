<?php

namespace App\Entity\SyllabusTemplate;

enum SubmissionKind: string
{
    case SharedTemplate = 'shared_template';
    case FacultyOffering = 'faculty_offering';
}
