<?php

declare(strict_types=1);

namespace rajmundtoth0\PHPStanForbidden\Services;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Use_;
use PHPStan\Analyser\Scope;
use rajmundtoth0\PHPStanForbidden\Models\ForbiddenNodes;

/**
 * @internal
 */
final class ForbiddenNodeService
{
    public function shouldAnalyseFile(ForbiddenNodes $config, string $file): bool
    {
        $file = str_replace('\\', '/', $file);

        foreach ($config->excludePaths as $needle) {
            if (str_contains($file, $needle)) {
                return false;
            }
        }

        if ($config->includePaths === []) {
            return true;
        }

        foreach ($config->includePaths as $needle) {
            if (str_contains($file, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public function findViolations(ForbiddenNodes $config, Node $node, Scope $scope, string $file): array
    {
        $violations = [];

        if ($config->useFromTests && $node instanceof Use_) {
            if (!$this->isTestFile($file) && $this->importsTestsNamespace($node)) {
                $violations[] = 'Forbidden code: importing Tests\\ namespace in non-test file.';
            }

            return $violations;
        }

        if ($config->forbidDynamicFunctionCalls && $this->isDynamicFunctionCall($node)) {
            $violations[] = 'Forbidden code: dynamic function call is not allowed.';

            return $violations;
        }

        $nodeType = $node->getType();
        $forbiddenNodes = $config->nodesForType($nodeType);

        $functionName = $this->extractFunctionName($node);
        $methodName = $this->extractMethodName($node);
        $classNames = $this->extractClassNames($node, $scope);

        foreach ($forbiddenNodes as $forbiddenNode) {

            if (!$forbiddenNode->matchesFile($file)) {
                continue;
            }

            if ($node instanceof FuncCall && $forbiddenNode->functions === null) {
                $violations[] = sprintf('Forbidden code: %s is not allowed.', $nodeType);
                continue;
            }

            if ($node instanceof FuncCall && $functionName !== null && $forbiddenNode->isFunctionForbidden($functionName)) {
                $violations[] = sprintf('Forbidden code: function %s() is not allowed.', $functionName);
                continue;
            }

            if (($node instanceof MethodCall || $node instanceof StaticCall) && $forbiddenNode->isMethodForbidden($classNames, $methodName)) {
                $violations[] = sprintf('Forbidden code: method %s is not allowed.', $this->formatMethodSignature($classNames, $methodName));
                continue;
            }

            if (!$node instanceof FuncCall && !$node instanceof MethodCall && !$node instanceof StaticCall && $forbiddenNode->functions === null) {
                $violations[] = sprintf('Forbidden code: %s is not allowed.', $nodeType);
            }
        }

        return $violations;
    }

    public function shouldProcessNode(ForbiddenNodes $config, Node $node): bool
    {
        if ($config->useFromTests && $node instanceof Use_) {
            return true;
        }

        if ($config->forbidDynamicFunctionCalls && $node instanceof FuncCall) {
            return true;
        }

        return $config->hasNodeType($node->getType());
    }

    private function extractFunctionName(Node $node): ?string
    {
        if (!$node instanceof FuncCall) {
            return null;
        }

        $name = $node->name;
        if ($name instanceof Name) {
            return $name->toString();
        }

        return null;
    }

    private function extractMethodName(Node $node): ?string
    {
        if ($node instanceof MethodCall || $node instanceof StaticCall) {
            return $node->name instanceof Identifier ? $node->name->toString() : null;
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function extractClassNames(Node $node, Scope $scope): array
    {
        if ($node instanceof MethodCall) {
            return $this->normalizeClassNames($scope->getType($node->var)->getObjectClassNames());
        }

        if ($node instanceof StaticCall) {
            if ($node->class instanceof Name) {
                return [$this->normalizeClassName($scope->resolveName($node->class))];
            }

            return $this->normalizeClassNames($scope->getType($node->class)->getObjectClassNames());
        }

        return [];
    }

    /**
     * @param list<string> $classNames
     * @return list<string>
     */
    private function normalizeClassNames(array $classNames): array
    {
        $normalized = [];

        foreach ($classNames as $className) {
            if ($className === '') {
                continue;
            }

            $normalized[] = $this->normalizeClassName($className);
        }

        return array_values(array_unique($normalized));
    }

    private function normalizeClassName(string $className): string
    {
        return ltrim($className, '\\');
    }

    /**
     * @param list<string> $classNames
     */
    private function formatMethodSignature(array $classNames, ?string $methodName): string
    {
        $methodName = $methodName ?? '<dynamic>';

        if ($classNames === []) {
            return sprintf('%s()', $methodName);
        }

        return sprintf('%s::%s()', $classNames[0], $methodName);
    }

    private function isDynamicFunctionCall(Node $node): bool
    {
        if (!$node instanceof FuncCall) {
            return false;
        }

        return !($node->name instanceof Name);
    }

    private function isTestFile(string $file): bool
    {
        $file = str_replace('\\', '/', $file);

        if (preg_match('~/tests?/~i', $file) === 1) {
            return true;
        }

        return (bool) preg_match('~(Test|Spec)\\.php$~', $file);
    }

    private function importsTestsNamespace(Use_ $use): bool
    {
        foreach ($use->uses as $useUse) {
            $name = $useUse->name->toString();
            if (str_starts_with($name, 'Tests\\') || $name === 'Tests') {
                return true;
            }
        }

        return false;
    }
}
