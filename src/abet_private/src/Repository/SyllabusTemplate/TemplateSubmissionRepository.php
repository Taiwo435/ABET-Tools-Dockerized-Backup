<?php

namespace App\Repository\SyllabusTemplate;

use App\Entity\Program;
use App\Entity\SyllabusTemplate\CompletenessStatus;
use App\Entity\SyllabusTemplate\ProposalOrigin;
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
    public function findCoordinatorTemplates(?CompletenessStatus $completeness = null): array
    {
        $builder = $this->createQueryBuilder('submission')
            ->addSelect('course', 'program', 'revision')
            ->innerJoin('submission.commonCourse', 'course')
            ->innerJoin('course.program', 'program')
            ->innerJoin('submission.workingRevision', 'revision')
            ->andWhere('submission.origin = :origin')
            ->setParameter('origin', ProposalOrigin::CoordinatorCreated->value)
            ->orderBy('course.courseSubject', 'ASC')
            ->addOrderBy('course.courseNumber', 'ASC')
            ->addOrderBy('submission.updatedAt', 'DESC');

        if ($completeness !== null) {
            $builder
                ->andWhere('revision.completenessStatus = :completeness')
                ->setParameter('completeness', $completeness->value);
        }

        return $builder->getQuery()->getResult();
    }

    /** @return list<TemplateSubmission> */
    public function findPendingFacultyReviews(?Program $program = null): array
    {
        $builder = $this->createQueryBuilder('submission')
            ->addSelect('course', 'program', 'submitter', 'submittedRevision')
            ->innerJoin('submission.commonCourse', 'course')
            ->innerJoin('course.program', 'program')
            ->innerJoin('submission.submittedBy', 'submitter')
            ->innerJoin('submission.submittedRevision', 'submittedRevision')
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

    public function countPendingFacultyReviews(?Program $program = null): int
    {
        $builder = $this->createQueryBuilder('submission')
            ->select('COUNT(DISTINCT submission.id)')
            ->leftJoin('submission.review', 'review')
            ->andWhere('submission.origin = :origin')
            ->andWhere('submission.status = :status')
            ->andWhere('submission.submittedAt IS NOT NULL')
            ->andWhere('review.id IS NULL')
            ->setParameter('origin', ProposalOrigin::FacultySubmission->value)
            ->setParameter('status', SubmissionStatus::Submitted->value);

        if ($program !== null) {
            $builder
                ->innerJoin('submission.commonCourse', 'course')
                ->andWhere('course.program = :programFilter')
                ->setParameter('programFilter', $program);
        }

        return (int) $builder->getQuery()->getSingleScalarResult();
    }

    /** @return list<Program> */
    public function findPendingFacultyReviewPrograms(): array
    {
        $pendingSubmissions = $this->createQueryBuilder('submission')
            ->addSelect('course', 'program')
            ->innerJoin('submission.commonCourse', 'course')
            ->innerJoin('course.program', 'program')
            ->leftJoin('submission.review', 'review')
            ->andWhere('submission.origin = :origin')
            ->andWhere('submission.status = :status')
            ->andWhere('submission.submittedAt IS NOT NULL')
            ->andWhere('review.id IS NULL')
            ->setParameter('origin', ProposalOrigin::FacultySubmission->value)
            ->setParameter('status', SubmissionStatus::Submitted->value)
            ->orderBy('program.name', 'ASC')
            ->addOrderBy('program.year', 'DESC')
            ->getQuery()
            ->getResult();

        $programs = [];
        foreach ($pendingSubmissions as $submission) {
            $program = $submission->getCommonCourse()->getProgram();
            $programs[$program->getId()] = $program;
        }

        return array_values($programs);
    }

    public function findPendingFacultyReview(int $id): ?TemplateSubmission
    {
        return $this->createQueryBuilder('submission')
            ->addSelect('course', 'program', 'submitter', 'submittedRevision', 'basedOnRevision', 'currentApprovedRevision')
            ->innerJoin('submission.commonCourse', 'course')
            ->innerJoin('course.program', 'program')
            ->innerJoin('submission.submittedBy', 'submitter')
            ->innerJoin('submission.submittedRevision', 'submittedRevision')
            ->leftJoin('submission.basedOnRevision', 'basedOnRevision')
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
}
