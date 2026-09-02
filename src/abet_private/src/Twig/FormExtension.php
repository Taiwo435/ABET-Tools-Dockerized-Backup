<?php
// src/Twig/AppExtension.php
namespace App\Twig;

use App\Service\Forms\FormFunctions;
use Twig\Attribute\AsTwigFunction;

class FormExtension
{

    public function __construct(
        private FormFunctions $helper
    )
    {}

    #[AsTwigFunction('loadFormPage')]
    public function loadFormPage(string $formName, string $pageName): array
    {
        return $this->helper->loadFormPage($formName, $pageName);
    }

    #[AsTwigFunction('formatReviewValue')]
    public function formatReviewValue(mixed $value): string
    {
        return $this->helper->formatReviewValue($value);
    }

    #[AsTwigFunction('decodeGridRows')]
    public function decodeGridRows(mixed $value): array
    {
        return $this->helper->decodeGridRows($value);
    }
}
?>