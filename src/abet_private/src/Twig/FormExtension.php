<?php
// src/Twig/AppExtension.php
namespace App\Twig;

use App\Service\Forms\FormFunctions;
use Twig\Attribute\AsTwigFunction;

class FormExtension
{
    public FormFunctions $helper;

    public function __construct(
        FormFunctions $formFunctions
    )
    {
        $this->helper = $formFunctions;
    }

    #[AsTwigFunction('formatReviewValue')]
    public function formatReview($v) : string
    {
        return $this->helper->formatReviewValue($v);
    }

    // #[AsTwigFunction('decodeGridRows')]
    // public function decodeGrid($v) : string
    // {
    //     return $this->helper->decodeGridRows();
    // }

}
?>