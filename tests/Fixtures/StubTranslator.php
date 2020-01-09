<?php

declare(strict_types=1);

namespace Yokai\SonataWorkflow\Tests\Fixtures;

use Symfony\Component\Translation\TranslatorInterface as LegacyTranslatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * `StubTranslator` provides a simple implementation for the "translator" service.
 */
if (interface_exists(TranslatorInterface::class)) {
    final class StubTranslator implements TranslatorInterface
    {
        public function trans($id, array $parameters = [], $domain = null, $locale = null): string
        {
            return '[trans]'.strtr($id, $parameters).'[/trans]';
        }
    }
} else {
    final class StubTranslator implements LegacyTranslatorInterface
    {
        public function trans($id, array $parameters = [], $domain = null, $locale = null)
        {
            return '[trans]'.$id.'[/trans]';
        }

        public function transChoice($id, $number, array $parameters = [], $domain = null, $locale = null)
        {
            return '[trans]'.$id.'[/trans]';
        }

        public function setLocale($locale)
        {
        }

        public function getLocale()
        {
        }
    }
}
