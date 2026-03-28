<?php

use App\Kernel;
use App\LegacyBridge;

require_once getenv("ABET_PRIVATE_DIR").'/vendor/autoload_runtime.php';

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
