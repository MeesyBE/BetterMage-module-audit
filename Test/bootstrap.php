<?php

declare(strict_types=1);

/**
 * Standalone unit/integration test bootstrap.
 *
 * Magento generates *Factory classes at runtime (generated/code) — they never
 * exist in the framework source, so standalone PHPUnit cannot mock them.
 * Inside a full Magento install the real generated classes take precedence
 * thanks to the class_exists() guards below (see VALKUILEN #1: use
 * interface_exists() for interfaces, class_exists() only for classes).
 */

namespace {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');

    require __DIR__ . '/../vendor/autoload.php';

    // Magento translation function used across modules.
    if (!function_exists('__')) {
        function __(string $text, ...$args): string
        {
            return $text;
        }
    }
}

namespace Magento\Framework\View\Result {
    if (!class_exists(PageFactory::class)) {
        /**
         * Minimal stand-in for the generated PageFactory. Only the signature
         * used by the code under test is declared; tests mock it anyway.
         */
        class PageFactory
        {
            public function create(array $data = []): Page
            {
                throw new \LogicException('Stub factory must be mocked in tests.');
            }
        }
    }
}