<?php

namespace App\Entity\SyllabusTemplate;

enum ReviewDecision: string
{
    case Approved = 'approved';
    case ApprovedWithEdits = 'approved_with_edits';
    case Denied = 'denied';
}
