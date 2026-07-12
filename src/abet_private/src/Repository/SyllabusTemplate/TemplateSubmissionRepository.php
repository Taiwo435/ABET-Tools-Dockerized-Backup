<?php

namespace App\Repository\SyllabusTemplate;

use App\Entity\SyllabusTemplate\CompletenessStatus;
use App\Entity\SyllabusTemplate\ProposalOrigin;
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
}
