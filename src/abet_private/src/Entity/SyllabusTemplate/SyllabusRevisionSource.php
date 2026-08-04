<?php

namespace App\Entity\SyllabusTemplate;

enum SyllabusRevisionSource: string
{
    case ManualEntry = 'manual_entry';
    case SharedTemplatePrefill = 'shared_template_prefill';
    case PdfExtraction = 'pdf_extraction';
    case ManualEdit = 'manual_edit';
}
