<?php

namespace App\Service\SyllabusTemplate;

use App\Entity\SyllabusTemplate\ProposalOrigin;
use App\Entity\SyllabusTemplate\RevisionAuthorType;
use App\Entity\SyllabusTemplate\SubmissionStatus;
use App\Entity\SyllabusTemplate\TemplateRevision;
use App\Entity\SyllabusTemplate\TemplateSubmission;
use App\Entity\User;
use App\Form\Model\CoordinatorTemplateData;

/**
 * Single application boundary for turning reviewed form/prefill data into
 * immutable syllabus revisions.
 */
final class SyllabusRevisionService
{
    public function addCoordinatorRevision(
        TemplateSubmission $submission,
        User $author,
        CoordinatorTemplateData $data,
    ): TemplateRevision {
        return $submission->addRevision(
            $author,
            RevisionAuthorType::Coordinator,
            $data->toContent(),
        );
    }

    public function addFacultyRevision(
        TemplateSubmission $submission,
        User $author,
        CoordinatorTemplateData $data,
        bool $updateBlankCourseIdentity = false,
    ): TemplateRevision {
        $revision = $submission->addRevision(
            $author,
            RevisionAuthorType::Faculty,
            $data->toContent(),
        );

        if ($updateBlankCourseIdentity) {
            if ($data->program === null) {
                throw new \InvalidArgumentException('A program is required to update blank faculty course identity.');
            }

            $submission->getCommonCourse()->updateBlankFacultyDraftDetails(
                $submission,
                $data->program,
                $data->courseSubject,
                $data->courseNumber,
                $data->courseName,
                $data->deliveryType,
            );
        }

        return $revision;
    }

    public function saveCoordinatorRevision(
        TemplateSubmission $submission,
        User $author,
        CoordinatorTemplateData $data,
    ): TemplateSubmission {
        if ($data->program === null) {
            throw new \InvalidArgumentException('A program is required to update shared course identity.');
        }

        $editableSubmission = $submission;
        if ($submission->getOrigin() === ProposalOrigin::FacultySubmission) {
            $editableSubmission = $submission->createCoordinatorRevisionDraft($author, $data->toContent());
        } elseif ($submission->getStatus() === SubmissionStatus::Approved) {
            $submission->beginCoordinatorRevision($author, $data->toContent());
        } else {
            $submission->addRevision($author, RevisionAuthorType::Coordinator, $data->toContent());
        }

        $editableSubmission->getCommonCourse()->updateDraftDetails(
            $editableSubmission,
            $data->program,
            $data->courseSubject,
            $data->courseNumber,
            $data->courseName,
            $data->deliveryType,
        );

        return $editableSubmission;
    }
}
