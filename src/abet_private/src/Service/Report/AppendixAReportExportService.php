<?php

namespace App\Service\Report;

use App\Entity\SyllabusTemplate\SubmissionKind;
use App\Entity\SyllabusTemplate\SubmissionStatus;
use App\Entity\SyllabusTemplate\TemplateRevision;

final class AppendixAReportExportService implements AppendixAReportExportBoundary
{
    public function export(iterable $revisions): AppendixAReportPayload
    {
        $courses = [];
        $seenCourseCodes = [];

        foreach ($revisions as $revision) {
            $submission = $revision->getSubmission();
            if ($submission->getApprovedRevision() !== $revision
                || !in_array($submission->getStatus(), [SubmissionStatus::Approved, SubmissionStatus::ApprovedWithEdits], true)) {
                throw new \DomainException('Appendix A exports require an approved syllabus revision.');
            }
            if (!$revision->isAppendixAReady()) {
                throw new \DomainException(sprintf(
                    'Revision %d is not Appendix A ready: %s.',
                    $revision->getRevisionNumber(),
                    implode(', ', $revision->getAppendixABlockingFields()),
                ));
            }

            $course = $submission->getCommonCourse();
            $courseCode = trim($course->getCourseSubject().' '.$course->getCourseNumber());
            $normalizedCode = strtoupper($courseCode);
            if (isset($seenCourseCodes[$normalizedCode])) {
                throw new \DomainException(sprintf(
                    'Appendix A export selection contains more than one syllabus for %s.',
                    $courseCode,
                ));
            }
            $seenCourseCodes[$normalizedCode] = true;

            $content = $revision->getContent();
            $offering = $submission->getCourseOffering();
            $deliveryType = $submission->getKind() === SubmissionKind::FacultyOffering && $offering !== null
                ? $offering->getDeliveryType()
                : $course->getDeliveryType();

            $courses[] = [
                'course_code' => $courseCode,
                'course_name' => $course->getCourseName(),
                'credits' => $content['credits'],
                'contact_hours' => $content['contact_hours'],
                'credit_category' => $content['credit_category'],
                'delivery_type' => $deliveryType->value,
                'instructors' => $content['instructors'],
                'textbooks' => $content['textbooks'] ?? [],
                'catalog_description' => $content['catalog_description'],
                'prerequisites' => $content['prerequisites'] ?? '',
                'course_type' => $content['course_type'],
                'specific_goals' => $content['specific_goals'],
                'student_outcomes' => $content['student_outcomes'],
                'topics_covered' => $content['topics_covered'],
            ];
        }

        return new AppendixAReportPayload($courses);
    }
}
