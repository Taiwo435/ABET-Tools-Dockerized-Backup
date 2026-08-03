<?php

namespace App\Entity\SyllabusTemplate;

enum SyllabusCompletenessPurpose: string
{
    case DraftSaveable = 'draft_saveable';
    case FacultySubmittable = 'faculty_submittable';
    case CoordinatorPublishable = 'coordinator_publishable';
    case AppendixAReady = 'appendix_a_ready';
}
