<?php

declare(strict_types=1);

namespace Yokai\SonataWorkflow\Tests\Fixtures;

use Symfony\Contracts\Translation\TranslatorInterface;

final class StubTranslator implements TranslatorInterface
{
    public function trans($id, array $parameters = [], $domain = null, $locale = null): string
    {
        return '[trans]' . strtr($id, $parameters) . '[/trans]';
    }

    public function getLocale(): string
    {
        return 'fa_KE';
    }
}
