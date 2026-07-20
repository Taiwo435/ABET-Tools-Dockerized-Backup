<?php

namespace Tests\Unit;

use App\Controller\SyllabusTemplate\AdminSyllabusTemplateController;
use App\Repository\SyllabusTemplate\TemplateSubmissionRepository;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class CoordinatorSyllabusReviewQueueTest extends TestCase
{
    public function testReviewQueueRouteIsAdminOnly(): void
    {
        $method = (new ReflectionClass(AdminSyllabusTemplateController::class))->getMethod('reviewQueue');
        $route = $method->getAttributes(Route::class)[0]->newInstance();
        $grant = $method->getAttributes(IsGranted::class)[0]->newInstance();

        self::assertSame('/admin/syllabus-template-reviews', $route->path);
        self::assertSame('app_admin_syllabus_template_reviews', $route->name);
        self::assertSame(['GET'], $route->methods);
        self::assertSame('ROLE_ADMIN', $grant->attribute);
    }

    public function testReviewDetailRouteIsAdminOnlyAndReadOnly(): void
    {
        $method = (new ReflectionClass(AdminSyllabusTemplateController::class))->getMethod('reviewDetail');
        $route = $method->getAttributes(Route::class)[0]->newInstance();
        $grant = $method->getAttributes(IsGranted::class)[0]->newInstance();

        self::assertSame('/admin/syllabus-template-reviews/{id}', $route->path);
        self::assertSame('app_admin_syllabus_template_review', $route->name);
        self::assertSame(['GET'], $route->methods);
        self::assertSame('ROLE_ADMIN', $grant->attribute);
    }

    public function testApproveUnchangedRouteIsAdminOnlyAndPostOnly(): void
    {
        $method = (new ReflectionClass(AdminSyllabusTemplateController::class))->getMethod('approveUnchanged');
        $route = $method->getAttributes(Route::class)[0]->newInstance();
        $grant = $method->getAttributes(IsGranted::class)[0]->newInstance();

        self::assertSame('/admin/syllabus-template-reviews/{id}/approve-unchanged', $route->path);
        self::assertSame('app_admin_syllabus_template_review_approve', $route->name);
        self::assertSame(['POST'], $route->methods);
        self::assertSame('ROLE_ADMIN', $grant->attribute);
    }

    public function testRemainingReviewRoutesAreAdminOnly(): void
    {
        $routes = [
            'deny' => ['/admin/syllabus-template-reviews/{id}/deny', 'app_admin_syllabus_template_review_deny', ['POST']],
            'approveWithEdits' => ['/admin/syllabus-template-reviews/{id}/edit', 'app_admin_syllabus_template_review_edit', ['GET', 'POST']],
            'reviewHistory' => ['/admin/syllabus-template-reviews/history', 'app_admin_syllabus_template_review_history', ['GET']],
        ];

        $reflection = new ReflectionClass(AdminSyllabusTemplateController::class);
        foreach ($routes as $methodName => [$path, $name, $methods]) {
            $method = $reflection->getMethod($methodName);
            $route = $method->getAttributes(Route::class)[0]->newInstance();
            $grant = $method->getAttributes(IsGranted::class)[0]->newInstance();

            self::assertSame($path, $route->path);
            self::assertSame($name, $route->name);
            self::assertSame($methods, $route->methods);
            self::assertSame('ROLE_ADMIN', $grant->attribute);
        }
    }

    public function testRepositoryDefinesPendingFacultyReviewQueryAndCount(): void
    {
        $reflection = new ReflectionClass(TemplateSubmissionRepository::class);
        $source = file_get_contents($reflection->getFileName());

        self::assertIsString($source);
        self::assertTrue($reflection->hasMethod('findPendingFacultyReviews'));
        self::assertTrue($reflection->hasMethod('countPendingFacultyReviews'));
        self::assertTrue($reflection->hasMethod('findPendingFacultyReview'));
        self::assertTrue($reflection->hasMethod('findPendingFacultyReviewPrograms'));
        self::assertTrue($reflection->hasMethod('findFacultyReview'));
        self::assertTrue($reflection->hasMethod('findReviewedFacultySubmissions'));
        self::assertTrue($reflection->hasMethod('findReviewedFacultyReviewPrograms'));
        self::assertStringContainsString('ProposalOrigin::FacultySubmission->value', $source);
        self::assertStringContainsString('SubmissionStatus::Submitted->value', $source);
        self::assertStringContainsString("submission.submittedAt IS NOT NULL", $source);
        self::assertStringContainsString("review.id IS NULL", $source);
        self::assertStringContainsString("orderBy('submission.submittedAt', 'ASC')", $source);
        self::assertStringContainsString('COUNT(DISTINCT submission.id)', $source);
        self::assertStringContainsString('course.program = :programFilter', $source);
        self::assertStringContainsString("addSelect('course', 'program')", $source);
        self::assertStringContainsString('$programs[$program->getId()] = $program;', $source);
        self::assertStringNotContainsString("select('DISTINCT program')", $source);
        self::assertStringContainsString("submission.id = :id", $source);
        self::assertStringContainsString("submission.basedOnRevision", $source);
        self::assertStringContainsString("course.currentApprovedRevision", $source);
    }

    public function testQueuePageShowsCountSubmissionContextAndEmptyState(): void
    {
        $queue = file_get_contents(dirname(__DIR__, 2).'/templates/syllabus_template/admin/review_queue.html.twig');
        $index = file_get_contents(dirname(__DIR__, 2).'/templates/syllabus_template/admin/index.html.twig');

        self::assertIsString($queue);
        self::assertIsString($index);
        self::assertStringContainsString('Faculty Syllabus Review Queue', $queue);
        self::assertStringContainsString('{{ pendingReviewCount }} pending', $queue);
        self::assertStringContainsString('submission.submittedBy.asurite', $queue);
        self::assertStringContainsString('submission.commonCourse.program.initials', $queue);
        self::assertStringContainsString('submission.submittedRevision.revisionNumber', $queue);
        self::assertStringContainsString('submission.submittedAt', $queue);
        self::assertStringNotContainsString('<th>Source</th>', $queue);
        self::assertStringNotContainsString('source-badge', $queue);
        self::assertStringContainsString('No faculty submissions are waiting', $queue);
        self::assertStringContainsString("path('app_admin_syllabus_template_review', {id: submission.id})", $queue);
        self::assertStringContainsString('Review submission', $queue);
        self::assertStringContainsString('name="program"', $queue);
        self::assertStringContainsString('Filter by program', $queue);
        self::assertStringContainsString('All programs', $queue);
        self::assertStringContainsString('selectedProgramId == program.id', $queue);
        self::assertStringContainsString('program.initials', $queue);
        self::assertStringContainsString("path('app_admin_syllabus_template_reviews')", $index);
        self::assertStringContainsString('{{ pendingReviewCount }}', $index);
    }

    public function testControllerUsesPendingQueryAndCountOnBothAdminPages(): void
    {
        $source = file_get_contents((new ReflectionClass(AdminSyllabusTemplateController::class))->getFileName());

        self::assertIsString($source);
        self::assertSame(2, substr_count($source, 'countPendingFacultyReviews('));
        self::assertStringContainsString("getInt('program')", $source);
        self::assertStringContainsString("'pendingSubmissions' => \$submissions->findPendingFacultyReviews(\$selectedProgram)", $source);
        self::assertStringContainsString("'pendingReviewCount' => \$submissions->countPendingFacultyReviews(\$selectedProgram)", $source);
        self::assertStringContainsString("'programs' => \$submissions->findPendingFacultyReviewPrograms()", $source);
        self::assertStringContainsString('$submissions->findFacultyReview($id)', $source);
        self::assertStringContainsString("'sharedTemplateChanged' => \$submission->hasSharedTemplateChanged()", $source);
        self::assertStringContainsString("isCsrfTokenValid('approve-syllabus-submission-'", $source);
        self::assertStringContainsString('ReviewDecision::Approved', $source);
        self::assertStringContainsString('$submission->recordReview($review, $submittedRevision)', $source);
        self::assertStringContainsString('$entityManager->persist($review)', $source);
        self::assertStringContainsString("isCsrfTokenValid('deny-syllabus-submission-'", $source);
        self::assertStringContainsString('ReviewDecision::Denied', $source);
        self::assertStringContainsString('$submission->recordDenial($review)', $source);
        self::assertStringContainsString('CoordinatorTemplateData::fromSubmission($submission)', $source);
        self::assertStringContainsString("['include_course_identity' => true]", $source);
        self::assertStringContainsString('ReviewDecision::ApprovedWithEdits', $source);
        self::assertStringContainsString('$submission->addRevision($user, RevisionAuthorType::Coordinator, $content)', $source);
        self::assertStringContainsString('$submission->getCommonCourse()->updateDuringFacultyReview(', $source);
        self::assertStringContainsString('courseDetailsChanged: $courseDetailsChanged', $source);
    }

    public function testReviewDetailShowsFrozenRevisionStaleWarningAndApproveUnchangedAction(): void
    {
        $detail = file_get_contents(dirname(__DIR__, 2).'/templates/syllabus_template/admin/review_detail.html.twig');

        self::assertIsString($detail);
        self::assertStringContainsString('submission.submittedRevision', $detail);
        self::assertStringContainsString('submission.submittedBy.asurite', $detail);
        self::assertStringContainsString('submission.commonCourse.program.initials', $detail);
        self::assertStringContainsString('content.creditHours', $detail);
        self::assertStringContainsString('content.courseCoordinators', $detail);
        self::assertStringContainsString('content.creditCategorization', $detail);
        self::assertStringContainsString('content.catalogDescription', $detail);
        self::assertStringContainsString('content.courseOutcomes', $detail);
        self::assertStringContainsString('Shared template changed since this proposal began.', $detail);
        self::assertStringContainsString('The proposal must be reconciled before approval.', $detail);
        self::assertStringContainsString('submitted faculty revision is frozen and remains read-only', $detail);
        self::assertStringContainsString("path('app_admin_syllabus_template_review_approve'", $detail);
        self::assertStringContainsString("csrf_token('approve-syllabus-submission-'", $detail);
        self::assertStringContainsString('Approve submission', $detail);
        self::assertStringContainsString('{% if sharedTemplateChanged %} disabled{% endif %}', $detail);
        self::assertStringContainsString('Deny submission', $detail);
        self::assertStringContainsString("csrf_token('deny-syllabus-submission-'", $detail);
        self::assertStringContainsString('Coordinator feedback is required', file_get_contents((new ReflectionClass(AdminSyllabusTemplateController::class))->getFileName()));
        self::assertStringContainsString("path('app_admin_syllabus_template_review_edit'", $detail);
        self::assertStringContainsString('class="button-primary review-approval-action"', $detail);
        self::assertStringContainsString('>Approve Unchanged</button>', $detail);
        self::assertStringContainsString('class="button-primary review-approval-action">Review and approve with edits</a>', $detail);
        self::assertStringContainsString('<details class="denial-disclosure"', $detail);
        self::assertStringContainsString('{% if denialError|default(null) %} open{% endif %}', $detail);
        self::assertStringContainsString('<summary class="button-primary review-approval-action">Deny submission</summary>', $detail);
        self::assertStringContainsString('class="button-primary review-approval-action">Confirm denial</button>', $detail);
        self::assertStringContainsString('.denial-disclosure > summary { list-style: none; cursor: pointer; }', $detail);
        self::assertStringContainsString('.review-approval-action { display: inline-flex;', $detail);
        self::assertStringContainsString('{% if submission.review %}', $detail);
    }

    public function testReviewEditAndHistoryTemplatesPreserveAuditContext(): void
    {
        $edit = file_get_contents(dirname(__DIR__, 2).'/templates/syllabus_template/admin/review_edit.html.twig');
        $history = file_get_contents(dirname(__DIR__, 2).'/templates/syllabus_template/admin/review_history.html.twig');

        self::assertIsString($edit);
        self::assertIsString($history);
        self::assertStringContainsString('Opening this page does not create a revision', $edit);
        self::assertStringContainsString('Approve with edits', $edit);
        self::assertStringContainsString("csrf_token('approve-with-edits-syllabus-submission-'", $edit);
        self::assertStringContainsString('frozen faculty-submitted revision', $edit);
        self::assertStringContainsString('form_row(form.program)', $edit);
        self::assertStringContainsString('form_row(form.courseSubject)', $edit);
        self::assertStringContainsString('form_row(form.courseNumber)', $edit);
        self::assertStringContainsString('form_row(form.courseName)', $edit);
        self::assertStringContainsString('form_row(form.deliveryType)', $edit);
        self::assertStringContainsString('Completed Syllabus Reviews', $history);
        self::assertStringContainsString('submission.review.decision.value', $history);
        self::assertStringContainsString('submission.review.reviewer.asurite', $history);
        self::assertStringContainsString('submission.decidedAt', $history);
        self::assertStringContainsString('submission.submittedRevision.revisionNumber', $history);
        self::assertStringContainsString('submission.approvedRevision', $history);
        self::assertStringContainsString('submission.review.comment', $history);
        self::assertStringContainsString("path('app_admin_syllabus_template_review'", $history);
    }
}
