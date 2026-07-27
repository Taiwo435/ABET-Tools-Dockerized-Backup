<?php

namespace App\Service\Report;

use App\Entity\SyllabusTemplate\TemplateRevision;

interface AppendixAReportExportBoundary
{
    /**
     * The caller explicitly selects one approved shared-baseline or
     * course-offering revision per course. This avoids silently preferring a
     * shared baseline or one offering over another.
     *
     * @param iterable<TemplateRevision> $revisions
     */
    public function export(iterable $revisions): AppendixAReportPayload;
}
