<?php

namespace App\Entity\SyllabusTemplate;

enum SubmissionStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case ApprovedWithEdits = 'approved_with_edits';
    case Denied = 'denied';
}
