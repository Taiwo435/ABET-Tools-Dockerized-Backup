<?php

namespace App\Repository\SyllabusTemplate;

use App\Entity\Program;
use App\Entity\SyllabusTemplate\CompletenessStatus;
use App\Entity\SyllabusTemplate\ProposalOrigin;
use App\Entity\SyllabusTemplate\ReviewDecision;
use App\Entity\SyllabusTemplate\SubmissionKind;
use App\Entity\SyllabusTemplate\SubmissionStatus;
use App\Entity\SyllabusTemplate\TemplateSubmission;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<TemplateSubmission> */
final class TemplateSubmissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TemplateSubmission::class);
    }

    /** @return list<TemplateSubmission> */
    public function findForProgramReadiness(Program $program): array
    {
        return $this->createQueryBuilder('submission')
            ->addSelect(
                'course',
                'program',
                'currentApprovedRevision',
                'workingRevision',
                'submittedRevision',
                'approvedRevision',
                'offering',
                'review',
            )
            ->innerJoin('submission.commonCourse', 'course')
            ->innerJoin('course.program', 'program')
            ->leftJoin('course.currentApprovedRevision', 'currentApprovedRevision')
            ->leftJoin('submission.workingRevision', 'workingRevision')
            ->leftJoin('submission.submittedRevision', 'submittedRevision')
            ->leftJoin('submission.approvedRevision', 'approvedRevision')
            ->leftJoin('submission.courseOffering', 'offering')
            ->leftJoin('submission.review', 'review')
            ->andWhere('course.program = :program')
            ->setParameter('program', $program)
            ->orderBy('course.courseSubject', 'ASC')
            ->addOrderBy('course.courseNumber', 'ASC')
            ->addOrderBy('submission.updatedAt', 'DESC')
            ->addOrderBy('submission.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<TemplateSubmission> */
    public function findManagedTemplates(
        ?CompletenessStatus $completeness = null,
        ?Program $program = null,
    ): array
    {
        $builder = $this->createQueryBuilder('submission')
            ->addSelect('course', 'program', 'revision', 'currentApprovedRevision')
            ->innerJoin('submission.commonCourse', 'course')
            ->innerJoin('course.program', 'program')
            ->innerJoin('submission.workingRevision', 'revision')
            ->leftJoin('course.currentApprovedRevision', 'currentApprovedRevision')
            ->andWhere('submission.kind = :sharedTemplateKind')
            ->andWhere('(submission.origin = :coordinatorOrigin AND submission.status = :draftStatus) OR submission.approvedRevision = currentApprovedRevision')
            ->setParameter('sharedTemplateKind', SubmissionKind::SharedTemplate->value)
            ->setParameter('coordinatorOrigin', ProposalOrigin::CoordinatorCreated->value)
            ->setParameter('draftStatus', SubmissionStatus::Draft->value)
            ->orderBy('course.courseSubject', 'ASC')
            ->addOrderBy('course.courseNumber', 'ASC')
            ->addOrderBy('submission.updatedAt', 'DESC')
            ->addOrderBy('submission.id', 'DESC');

        if ($program !== null) {
            $builder
                ->andWhere('course.program = :programFilter')
                ->setParameter('programFilter', $program);
        }

        $managedByCourse = [];
        foreach ($builder->getQuery()->getResult() as $submission) {
            $courseId = $submission->getCommonCourse()->getId();
            $existing = $managedByCourse[$courseId] ?? null;
            $isCoordinatorDraft = $submission->getOrigin() === ProposalOrigin::CoordinatorCreated
                && $submission->getStatus() === SubmissionStatus::Draft;
            $existingIsCoordinatorDraft = $existing !== null
                && $existing->getOrigin() === ProposalOrigin::CoordinatorCreated
                && $existing->getStatus() === SubmissionStatus::Draft;

            if ($existing === null || ($isCoordinatorDraft && !$existingIsCoordinatorDraft)) {
                $managedByCourse[$courseId] = $submission;
            }
        }

        $templates = array_values($managedByCourse);
        if ($completeness !== null) {
            $templates = array_values(array_filter(
                $templates,
                static fn (TemplateSubmission $submission): bool => $submission->getWorkingRevision()?->getCompletenessStatus() === $completeness,
            ));
        }

        return $templates;
    }

    /** @return list<TemplateSubmission> */
    public function findPendingFacultyReviews(?Program $program = null): array
    {
        $builder = $this->createQueryBuilder('submission')
            ->addSelect('course', 'program', 'submitter', 'submittedRevision', 'offering')
            ->innerJoin('submission.commonCourse', 'course')
            ->innerJoin('course.program', 'program')
            ->innerJoin('submission.submittedBy', 'submitter')
            ->innerJoin('submission.submittedRevision', 'submittedRevision')
            ->leftJoin('submission.courseOffering', 'offering')
            ->leftJoin('submission.review', 'review')
            ->andWhere('submission.origin = :origin')
            ->andWhere('submission.status = :status')
            ->andWhere('submission.submittedAt IS NOT NULL')
            ->andWhere('review.id IS NULL')
            ->setParameter('origin', ProposalOrigin::FacultySubmission->value)
            ->setParameter('status', SubmissionStatus::Submitted->value)
            ->orderBy('submission.submittedAt', 'ASC')
            ->addOrderBy('submission.id', 'ASC');

        if ($program !== null) {
            $builder
                ->andWhere('course.program = :programFilter')
                ->setParameter('programFilter', $program);
        }

        return $builder->getQuery()->getResult();
    }

    public function findPendingFacultyReview(int $id): ?TemplateSubmission
    {
        return $this->createQueryBuilder('submission')
            ->addSelect('course', 'program', 'submitter', 'submittedRevision', 'basedOnRevision', 'currentApprovedRevision', 'offering')
            ->innerJoin('submission.commonCourse', 'course')
            ->innerJoin('course.program', 'program')
            ->innerJoin('submission.submittedBy', 'submitter')
            ->innerJoin('submission.submittedRevision', 'submittedRevision')
            ->leftJoin('submission.basedOnRevision', 'basedOnRevision')
            ->leftJoin('submission.courseOffering', 'offering')
            ->leftJoin('course.currentApprovedRevision', 'currentApprovedRevision')
            ->leftJoin('submission.review', 'review')
            ->andWhere('submission.id = :id')
            ->andWhere('submission.origin = :origin')
            ->andWhere('submission.status = :status')
            ->andWhere('submission.submittedAt IS NOT NULL')
            ->andWhere('review.id IS NULL')
            ->setParameter('id', $id)
            ->setParameter('origin', ProposalOrigin::FacultySubmission->value)
            ->setParameter('status', SubmissionStatus::Submitted->value)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findFacultyReview(int $id): ?TemplateSubmission
    {
        return $this->createQueryBuilder('submission')
            ->addSelect('course', 'program', 'submitter', 'submittedRevision', 'approvedRevision', 'basedOnRevision', 'currentApprovedRevision', 'offering', 'review', 'reviewer')
            ->innerJoin('submission.commonCourse', 'course')
            ->innerJoin('course.program', 'program')
            ->innerJoin('submission.submittedBy', 'submitter')
            ->innerJoin('submission.submittedRevision', 'submittedRevision')
            ->leftJoin('submission.approvedRevision', 'approvedRevision')
            ->leftJoin('submission.basedOnRevision', 'basedOnRevision')
            ->leftJoin('submission.courseOffering', 'offering')
            ->leftJoin('course.currentApprovedRevision', 'currentApprovedRevision')
            ->leftJoin('submission.review', 'review')
            ->leftJoin('review.reviewer', 'reviewer')
            ->andWhere('submission.id = :id')
            ->andWhere('submission.origin = :origin')
            ->andWhere('submission.status IN (:statuses)')
            ->andWhere('submission.submittedAt IS NOT NULL')
            ->setParameter('id', $id)
            ->setParameter('origin', ProposalOrigin::FacultySubmission->value)
            ->setParameter('statuses', [
                SubmissionStatus::Submitted->value,
                SubmissionStatus::Approved->value,
                SubmissionStatus::ApprovedWithEdits->value,
                SubmissionStatus::Denied->value,
            ])
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return list<TemplateSubmission> */
    public function findReviewedFacultySubmissions(?Program $program = null, ?ReviewDecision $decision = null): array
    {
        $builder = $this->createQueryBuilder('submission')
            ->addSelect('course', 'program', 'submitter', 'submittedRevision', 'approvedRevision', 'offering', 'review', 'reviewer')
            ->innerJoin('submission.commonCourse', 'course')
            ->innerJoin('course.program', 'program')
            ->innerJoin('submission.submittedBy', 'submitter')
            ->innerJoin('submission.submittedRevision', 'submittedRevision')
            ->leftJoin('submission.approvedRevision', 'approvedRevision')
            ->leftJoin('submission.courseOffering', 'offering')
            ->innerJoin('submission.review', 'review')
            ->innerJoin('review.reviewer', 'reviewer')
            ->andWhere('submission.origin = :origin')
            ->andWhere('submission.status IN (:statuses)')
            ->andWhere('submission.decidedAt IS NOT NULL')
            ->setParameter('origin', ProposalOrigin::FacultySubmission->value)
            ->setParameter('statuses', [
                SubmissionStatus::Approved->value,
                SubmissionStatus::ApprovedWithEdits->value,
                SubmissionStatus::Denied->value,
            ])
            ->orderBy('submission.decidedAt', 'DESC')
            ->addOrderBy('submission.id', 'DESC');

        if ($program !== null) {
            $builder
                ->andWhere('course.program = :programFilter')
                ->setParameter('programFilter', $program);
        }
        if ($decision !== null) {
            $builder
                ->andWhere('review.decision = :decisionFilter')
                ->setParameter('decisionFilter', $decision->value);
        }

        return $builder->getQuery()->getResult();
    }

}
