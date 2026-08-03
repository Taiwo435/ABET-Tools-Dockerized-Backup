<?php

namespace App\Entity\SyllabusTemplate;

enum ProposalOrigin: string
{
    case FacultySubmission = 'faculty_submission';
    case CoordinatorCreated = 'coordinator_created';
}
