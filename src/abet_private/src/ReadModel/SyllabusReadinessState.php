<?php

declare(strict_types=1);

namespace App\ReadModel;

/**
 * Represents the readiness state of a course syllabus.
 */
enum SyllabusReadinessState: string
{
    case NoSharedTemplate = 'No shared template';
    case SharedTemplateNeedsPublicationFields = 'Shared template needs publication fields';
    case SharedTemplateReadyToPublish = 'Shared template ready to publish';
    case SharedTemplatePublishedNoOffering = 'Shared template published; no offering submission';
    case FacultyDraftNeedsSubmissionFields = 'Faculty draft needs submission fields';
    case FacultyDraftReadyToSubmit = 'Faculty draft ready to submit';
    case AwaitingCoordinatorReview = 'Awaiting coordinator review';
    case ApprovedAppendixAReady = 'Approved and Appendix A ready';
    case ApprovedAppendixAIncomplete = 'Approved, Appendix A evidence incomplete';
    case DeniedNeedsRevision = 'Denied; faculty revision required';

    /**
     * Maps the state to its corresponding high-level category.
     * Counts must be shown for: Ready, Blocked, Awaiting review, Missing.
     */
    public function getCategory(): string
    {
        return match ($this) {
            self::ApprovedAppendixAReady => 'Ready',
            self::AwaitingCoordinatorReview => 'Awaiting review',
            self::NoSharedTemplate, self::SharedTemplatePublishedNoOffering => 'Missing',
            self::SharedTemplateNeedsPublicationFields,
            self::SharedTemplateReadyToPublish,
            self::FacultyDraftNeedsSubmissionFields,
            self::FacultyDraftReadyToSubmit,
            self::ApprovedAppendixAIncomplete,
            self::DeniedNeedsRevision => 'Blocked',
        };
    }
}
