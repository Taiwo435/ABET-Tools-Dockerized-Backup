<?php

declare(strict_types=1);

namespace App\ReadModel;

/**
 * Represents the readiness state of a course syllabus.
 */
enum SyllabusReadinessState: string
{
    case NoSharedTemplate = 'No shared template';
    case SharedTemplateIncomplete = 'Shared template incomplete';
    case SharedTemplatePublished = 'Shared template published';
    case FacultyDraftInProgress = 'Faculty draft in progress';
    case SubmittedForReview = 'Submitted for review';
    case ApprovedAndReadyForAppendixA = 'Approved and ready for Appendix A';
    case ApprovedAppendixAIncomplete = 'Approved, Appendix A evidence incomplete';
    case DeniedWithFeedback = 'Denied with feedback';

    /**
     * Maps the state to its corresponding high-level category.
     * Counts must be shown for: Ready, Blocked, Awaiting review, Missing.
     */
    public function getCategory(): string
    {
        return match ($this) {
            self::ApprovedAndReadyForAppendixA => 'Ready',
            self::SubmittedForReview => 'Awaiting review',
            self::NoSharedTemplate, self::SharedTemplatePublished => 'Missing',
            self::SharedTemplateIncomplete,
            self::FacultyDraftInProgress,
            self::ApprovedAppendixAIncomplete,
            self::DeniedWithFeedback => 'Blocked',
        };
    }
}
