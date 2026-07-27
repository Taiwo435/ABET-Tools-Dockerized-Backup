<?php

namespace App\Service\SyllabusTemplate;

use App\Entity\SyllabusTemplate\CommonCourse;
use App\Entity\SyllabusTemplate\TemplateRevision;
use App\Entity\SyllabusTemplate\TemplateSubmission;
use App\Form\Model\CoordinatorTemplateData;

/**
 * Maps every syllabus source into the same editable form model.
 *
 * PDF extraction can call fromExtractedContent() once an adapter produces the
 * canonical syllabus content contract.
 */
final class SyllabusPrefillService
{
    /** @param array<string, mixed> $content */
    public function fromExtractedContent(array $content, ?CommonCourse $course = null): CoordinatorTemplateData
    {
        $data = CoordinatorTemplateData::fromContent($content);
        if ($course !== null) {
            $this->applyCourseIdentity($data, $course);
        }

        return $data;
    }

    public function fromRevision(TemplateRevision $revision, ?CommonCourse $course = null): CoordinatorTemplateData
    {
        return $this->fromExtractedContent($revision->getContent(), $course);
    }

    public function fromSubmission(TemplateSubmission $submission): CoordinatorTemplateData
    {
        $revision = $submission->getWorkingRevision();
        $data = $revision === null
            ? new CoordinatorTemplateData()
            : $this->fromRevision($revision);
        $this->applyCourseIdentity($data, $submission->getCommonCourse());

        return $data;
    }

    private function applyCourseIdentity(CoordinatorTemplateData $data, CommonCourse $course): void
    {
        $data->program = $course->getProgram();
        $data->courseSubject = $course->getCourseSubject();
        $data->courseNumber = $course->getCourseNumber();
        $data->courseName = $course->getCourseName();
        $data->deliveryType = $course->getDeliveryType();
    }
}
