<?php

declare(strict_types=1);

namespace Tests\Support;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use rajmundtoth0\PHPStanForbidden\Rules\ForbiddenNodeRule;
use rajmundtoth0\PHPStanForbidden\Services\ForbiddenNodeService;

/**
 * @extends RuleTestCase<ForbiddenNodeRule>
 */
class RuleHarness extends RuleTestCase
{
    /** @var array<string,mixed> */
    private array $config = [];

    protected function getRule(): Rule
    {
        return new ForbiddenNodeRule($this->config, new ForbiddenNodeService());
    }

    /**
     * @param array<string,mixed> $config
     */
    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    /**
     * @param list<string> $files
     * @param list<array{0:string,1:int}> $expectedErrors
     */
    public function runAnalyse(array $files, array $expectedErrors): void
    {
        $this->analyse($files, array_map(static fn (array $e) => [$e[0], $e[1]], $expectedErrors));
    }
}
