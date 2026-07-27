<?php

namespace App\Service\SyllabusTemplate;

use App\Entity\SyllabusTemplate\ProposalOrigin;
use App\Entity\SyllabusTemplate\RevisionAuthorType;
use App\Entity\SyllabusTemplate\SubmissionStatus;
use App\Entity\SyllabusTemplate\SyllabusProvenanceV1;
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
        ?SyllabusProvenanceV1 $provenance = null,
    ): TemplateRevision {
        return $submission->addRevision(
            $author,
            RevisionAuthorType::Coordinator,
            $data->toContent(),
            $provenance ?? $this->defaultProvenance($submission),
        );
    }

    public function addFacultyRevision(
        TemplateSubmission $submission,
        User $author,
        CoordinatorTemplateData $data,
        bool $updateBlankCourseIdentity = false,
        bool $updateOfferingIdentity = false,
        ?SyllabusProvenanceV1 $provenance = null,
    ): TemplateRevision {
        $revision = $submission->addRevision(
            $author,
            RevisionAuthorType::Faculty,
            $data->toContent(),
            $provenance ?? $this->defaultProvenance($submission),
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

        if ($updateOfferingIdentity) {
            $offering = $submission->getCourseOffering();
            if ($offering === null) {
                throw new \InvalidArgumentException('An offering target is required to update offering identity.');
            }
            $offering->updateDraftDetails(
                $submission,
                $data->academicYear,
                $data->term,
                $data->section,
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

        $provenance = $this->defaultProvenance($submission);
        $editableSubmission = $submission;
        if ($submission->getOrigin() === ProposalOrigin::FacultySubmission) {
            $editableSubmission = $submission->createCoordinatorRevisionDraft($author, $data->toContent(), $provenance);
        } elseif ($submission->getStatus() === SubmissionStatus::Approved) {
            $submission->beginCoordinatorRevision($author, $data->toContent(), $provenance);
        } else {
            $submission->addRevision($author, RevisionAuthorType::Coordinator, $data->toContent(), $provenance);
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

    private function defaultProvenance(TemplateSubmission $submission): SyllabusProvenanceV1
    {
        $workingRevision = $submission->getWorkingRevision();
        if ($workingRevision !== null) {
            return SyllabusProvenanceV1::manualEdit($workingRevision);
        }

        $basedOnRevision = $submission->getBasedOnRevision();
        if ($basedOnRevision !== null) {
            return SyllabusProvenanceV1::sharedTemplatePrefill($basedOnRevision);
        }

        return SyllabusProvenanceV1::manualEntry();
    }
}
