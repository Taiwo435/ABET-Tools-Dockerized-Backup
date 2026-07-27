<?php

namespace App\Service\Report;

use App\Entity\SyllabusTemplate\TemplateRevision;

interface AppendixAReportExportBoundary
{
    /**
     * The caller explicitly selects one approved revision per course. This
     * avoids silently preferring one offering over another.
     *
     * @param iterable<TemplateRevision> $revisions
     */
    public function export(iterable $revisions): AppendixAReportPayload;
}
