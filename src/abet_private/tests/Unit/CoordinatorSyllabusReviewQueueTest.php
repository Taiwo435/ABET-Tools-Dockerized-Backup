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

    public function testRepositoryDefinesProgramScopedWorkspaceReviewQueries(): void
    {
        $reflection = new ReflectionClass(TemplateSubmissionRepository::class);
        $source = file_get_contents($reflection->getFileName());

        self::assertIsString($source);
        self::assertTrue($reflection->hasMethod('findPendingFacultyReviews'));
        self::assertTrue($reflection->hasMethod('findPendingFacultyReview'));
        self::assertTrue($reflection->hasMethod('findFacultyReview'));
        self::assertTrue($reflection->hasMethod('findReviewedFacultySubmissions'));
        self::assertStringContainsString('ProposalOrigin::FacultySubmission->value', $source);
        self::assertStringContainsString('SubmissionStatus::Submitted->value', $source);
        self::assertStringContainsString("submission.submittedAt IS NOT NULL", $source);
        self::assertStringContainsString("review.id IS NULL", $source);
        self::assertStringContainsString("orderBy('submission.submittedAt', 'ASC')", $source);
        self::assertStringContainsString('course.program = :programFilter', $source);
        self::assertStringContainsString("submission.id = :id", $source);
        self::assertStringContainsString("submission.basedOnRevision", $source);
        self::assertStringContainsString("course.currentApprovedRevision", $source);
    }

    public function testWorkspaceShowsPendingAndCompletedReviews(): void
    {
        $index = file_get_contents(dirname(__DIR__, 2).'/templates/syllabus_template/admin/index.html.twig');

        self::assertIsString($index);
        self::assertStringContainsString('id="pending-review"', $index);
        self::assertStringContainsString('id="review-history"', $index);
        self::assertStringContainsString('submission.submittedBy.asurite', $index);
        self::assertStringContainsString('submission.submittedAt', $index);
        self::assertStringContainsString('submission.review.decision.value', $index);
        self::assertStringContainsString('submission.review.reviewer.asurite', $index);
        self::assertStringContainsString('submission.review.comment', $index);
        self::assertStringContainsString("path('app_admin_syllabus_template_review', {id: submission.id})", $index);
        self::assertStringContainsString('Review submission', $index);
        self::assertStringContainsString('{{ pendingReviewCount }}', $index);
        self::assertStringContainsString(
            '<span class="metric-number">{{ pendingReviewCount }}</span>',
            $index,
        );
        self::assertStringNotContainsString(
            "<span class=\"metric-number\">{{ readinessCounts['Awaiting review'] }}</span>",
            $index,
        );
    }

    public function testControllerUsesProgramScopedReviewQueriesAndCompatibilityRedirects(): void
    {
        $source = file_get_contents((new ReflectionClass(AdminSyllabusTemplateController::class))->getFileName());

        self::assertIsString($source);
        self::assertSame(1, substr_count($source, 'findPendingFacultyReviews('));
        self::assertStringContainsString("getInt('program')", $source);
        self::assertStringContainsString('$submissions->findPendingFacultyReviews($selectedProgram)', $source);
        self::assertStringContainsString('$submissions->findReviewedFacultySubmissions($selectedProgram)', $source);
        self::assertStringContainsString("'pendingReviewCount' => count(\$pendingSubmissions)", $source);
        self::assertStringContainsString("'_fragment' => 'pending-review'", $source);
        self::assertStringContainsString("'_fragment' => 'review-history'", $source);
        self::assertStringContainsString('$submissions->findFacultyReview($id)', $source);
        self::assertStringContainsString("'sharedTemplateChanged' => \$submission->hasSharedTemplateChanged()", $source);
        self::assertStringContainsString("isCsrfTokenValid('approve-syllabus-submission-'", $source);
        self::assertStringContainsString('ReviewDecision::Approved', $source);
        self::assertStringContainsString('$submission->recordReview($review, $submittedRevision)', $source);
        self::assertSame(
            3,
            substr_count($source, 'allowCoordinatorSelfReviewForDemonstration: true'),
        );
        self::assertStringContainsString('$entityManager->persist($review)', $source);
        self::assertStringContainsString("isCsrfTokenValid('deny-syllabus-submission-'", $source);
        self::assertStringContainsString('ReviewDecision::Denied', $source);
        self::assertStringContainsString('$submission->recordDenial($review)', $source);
        self::assertStringContainsString('$this->prefill->fromSubmission($submission)', $source);
        self::assertStringContainsString("['include_course_identity' => true]", $source);
        self::assertStringContainsString('ReviewDecision::ApprovedWithEdits', $source);
        self::assertStringContainsString('$this->revisions->addCoordinatorRevision($submission, $user, $data)', $source);
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
        self::assertStringContainsString('content.credits', $detail);
        self::assertStringContainsString('content.course_coordinators', $detail);
        self::assertStringContainsString('content.credit_category', $detail);
        self::assertStringContainsString('content.catalog_description', $detail);
        self::assertStringContainsString('content.course_outcomes', $detail);
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
        self::assertStringContainsString('PDF extraction provenance', $detail);
        self::assertStringContainsString('revision.sourceProvenance.toArray', $detail);
        self::assertStringContainsString("syllabus_template/_lifecycle_badges.html.twig", $detail);
    }

    public function testReviewEditAndWorkspaceHistoryPreserveAuditContext(): void
    {
        $edit = file_get_contents(dirname(__DIR__, 2).'/templates/syllabus_template/admin/review_edit.html.twig');
        $workspace = file_get_contents(dirname(__DIR__, 2).'/templates/syllabus_template/admin/index.html.twig');

        self::assertIsString($edit);
        self::assertIsString($workspace);
        self::assertStringContainsString('Opening this page does not create a revision', $edit);
        self::assertStringContainsString('Approve with edits', $edit);
        self::assertStringContainsString("csrf_token('approve-with-edits-syllabus-submission-'", $edit);
        self::assertStringContainsString('frozen faculty-submitted revision', $edit);
        self::assertStringContainsString('form_row(form.program)', $edit);
        self::assertStringContainsString('form_row(form.courseSubject)', $edit);
        self::assertStringContainsString('form_row(form.courseNumber)', $edit);
        self::assertStringContainsString('form_row(form.courseName)', $edit);
        self::assertStringContainsString('form_row(form.deliveryType)', $edit);
        self::assertStringContainsString('Completed reviews', $workspace);
        self::assertStringContainsString('submission.review.decision.value', $workspace);
        self::assertStringContainsString('submission.review.reviewer.asurite', $workspace);
        self::assertStringContainsString('submission.decidedAt', $workspace);
        self::assertStringContainsString('submission.review.comment', $workspace);
        self::assertStringContainsString("path('app_admin_syllabus_template_review'", $workspace);
    }
}
