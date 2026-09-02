<?php
// src/Twig/AppExtension.php
namespace App\Twig;

use App\Service\Forms\CoordinatorFormLoader;
use App\Service\Forms\FormFunctions;
use Twig\Attribute\AsTwigFunction;

class FormExtensionLoaders
{

    public function __construct(
        private CoordinatorFormLoader $loader
    )
    {}

    #[AsTwigFunction('loadValuesCoordinator')]
    public function loadValuesCoordinator(string $pageName): array
    {
        return $this->loader->loadValues($pageName);
    }
}
?>