<?php

declare(strict_types=1);

namespace rajmundtoth0\PHPStanForbidden\Rules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use rajmundtoth0\PHPStanForbidden\Models\ForbiddenNodes;
use rajmundtoth0\PHPStanForbidden\Services\ForbiddenNodeService;

/**
 * @implements Rule<Node>
 */
final class ForbiddenNodeRule implements Rule
{
    private readonly ForbiddenNodes $config;

    /**
     * @param array<string,mixed> $config
     */
    public function __construct(
        array $config,
        private readonly ForbiddenNodeService $forbiddenNodeService,
    ) {
        $this->config = ForbiddenNodes::fromArray($config);
    }

    public function getNodeType(): string
    {
        return Node::class;
    }

    /**
     * @return list<RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $file = $scope->getFile();
        if (!$this->forbiddenNodeService->shouldAnalyseFile($this->config, $file)) {
            return [];
        }

        if (!$this->forbiddenNodeService->shouldProcessNode($this->config, $node)) {
            return [];
        }

        $errors = [];

        foreach ($this->forbiddenNodeService->findViolations($this->config, $node, $scope, $file) as $violation) {
            $errors[] = $this->buildError($violation, $node);
        }

        return $errors;
    }

    private function buildError(string $message, Node $node): RuleError
    {
        $builder = RuleErrorBuilder::message($message)
            ->identifier('forbiddenNode');

        if ($this->config->nonIgnorable) {
            $builder->nonIgnorable();
        }

        return $builder->line($node->getStartLine())->build();
    }
}
