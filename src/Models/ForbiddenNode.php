<?php

declare(strict_types=1);

namespace rajmundtoth0\PHPStanForbidden\Models;

use rajmundtoth0\PHPStanForbidden\Enums\NodeType;
use rajmundtoth0\PHPStanForbidden\Trait\PathNormalizer;

/**
 * @internal
 */
final class ForbiddenNode
{
    use PathNormalizer;

    /** @var null|list<string> */
    public readonly ?array $functions;

    /** @var null|list<ForbiddenMethodPattern> */
    public readonly ?array $methods;

    /** @var null|list<ForbiddenClassPattern> */
    public readonly ?array $classes;

    public readonly bool $matchesAll;

    /** @var list<string> */
    public readonly array $includePaths;

    /** @var list<string> */
    public readonly array $excludePaths;

    /**
     * @param null|array<mixed> $functions
     * @param null|list<ForbiddenMethodPattern> $methods
     * @param null|list<ForbiddenClassPattern> $classes
     * @param list<string> $includePaths
     * @param list<string> $excludePaths
     */
    public function __construct(
        public readonly NodeType $nodeType,
        ?array $functions,
        ?array $methods = null,
        ?array $classes = null,
        array $includePaths = [],
        array $excludePaths = [],
    ) {
        $this->functions    = null === $functions ? null : $this->normalizeFunctions($functions);
        $this->methods      = $methods;
        $this->classes      = $classes;
        $this->matchesAll   = $this->inferMatchesAll();
        $this->includePaths = $this->normalizePaths($includePaths);
        $this->excludePaths = $this->normalizePaths($excludePaths);
    }

    /**
     * @param array<string,mixed> $raw
     */
    public static function fromArray(array $raw): ?self
    {
        $nodeTypeValue = $raw['type'] ?? null;
        if (!is_string($nodeTypeValue)) {
            return null;
        }

        $nodeType = NodeType::tryFrom($nodeTypeValue);
        if (null === $nodeType) {
            return null;
        }

        $functions = $raw['functions'] ?? null;
        if (null !== $functions && !is_array($functions)) {
            $functions = null;
        } elseif (is_array($functions)) {
            $functions = array_values($functions);
        }

        $methods = self::parseMethodPatterns($raw, $nodeType, $functions);
        $classes = self::parseClassPatterns($raw, $nodeType);

        return new self(
            nodeType: $nodeType,
            functions: $functions,
            methods: $methods,
            classes: $classes,
            includePaths: self::readPaths($raw, 'include_paths', 'includePaths'),
            excludePaths: self::readPaths($raw, 'exclude_paths', 'excludePaths'),
        );
    }

    public function isFunctionForbidden(?string $functionName): bool
    {
        if (null === $this->functions) {
            return true;
        }

        if (null === $functionName) {
            return false;
        }

        return in_array(strtolower($functionName), $this->functions, true);
    }

    /**
     * @param list<string> $classNames
     */
    public function isMethodForbidden(array $classNames, ?string $methodName): bool
    {
        if (null === $this->methods) {
            return true;
        }

        if (null === $methodName) {
            return false;
        }

        if ([] === $classNames) {
            $classNames = [null];
        }

        foreach ($this->methods as $pattern) {
            foreach ($classNames as $className) {
                if ($pattern->matches($className, $methodName)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param list<string> $classNames
     */
    public function findForbiddenClass(array $classNames): ?string
    {
        if (null === $this->classes) {
            return $classNames[0] ?? null;
        }

        foreach ($classNames as $className) {
            foreach ($this->classes as $pattern) {
                if ($pattern->matches($className)) {
                    return $className;
                }
            }
        }

        return null;
    }

    public function matchesFile(string $file): bool
    {
        $file = str_replace('\\', '/', $file);

        foreach ($this->excludePaths as $needle) {
            if (str_contains($file, $needle)) {
                return false;
            }
        }

        if ([] === $this->includePaths) {
            return true;
        }

        foreach ($this->includePaths as $needle) {
            if (str_contains($file, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<mixed> $functions
     * @return list<string>
     */
    private function normalizeFunctions(array $functions): array
    {
        $normalized = [];

        foreach ($functions as $function) {
            if (!is_string($function) || '' === $function) {
                continue;
            }

            $normalized[] = strtolower($function);
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param array<string,mixed> $raw
     * @return null|list<ForbiddenClassPattern>
     */
    private static function parseClassPatterns(array $raw, NodeType $nodeType): ?array
    {
        if (!self::supportsClassPatterns($nodeType)) {
            return [];
        }

        if (!array_key_exists('classes', $raw)) {
            return null;
        }

        if (null === $raw['classes']) {
            return null;
        }

        if (!is_array($raw['classes'])) {
            return [];
        }

        $patterns = [];

        foreach ($raw['classes'] as $entry) {
            if (!is_string($entry) || '' === $entry) {
                continue;
            }

            $patterns[] = new ForbiddenClassPattern($entry);
        }

        return self::uniqueClassPatterns($patterns);
    }

    /**
     * @param array<string,mixed> $raw
     * @param null|array<mixed> $legacyFunctions
     * @return null|list<ForbiddenMethodPattern>
     */
    private static function parseMethodPatterns(array $raw, NodeType $nodeType, ?array $legacyFunctions): ?array
    {
        if (!self::supportsMethodPatterns($nodeType)) {
            return [];
        }

        if (array_key_exists('methods', $raw)) {
            if (null === $raw['methods']) {
                return null; // all method/static calls are forbidden for this node type
            }

            if (!is_array($raw['methods'])) {
                return [];
            }

            $patterns = [];
            foreach ($raw['methods'] as $entry) {
                if (is_string($entry) && '' !== $entry) {
                    $patterns[] = new ForbiddenMethodPattern('*', $entry);

                    continue;
                }

                if (!is_array($entry)) {
                    continue;
                }

                $methodPattern = $entry['method'] ?? $entry['method_pattern'] ?? null;
                if (!is_string($methodPattern) || '' === $methodPattern) {
                    continue;
                }

                $classPattern = $entry['class'] ?? $entry['class_pattern'] ?? '*';
                if (!is_string($classPattern) || '' === $classPattern) {
                    $classPattern = '*';
                }

                $patterns[] = new ForbiddenMethodPattern($classPattern, $methodPattern);
            }

            return self::uniqueMethodPatterns($patterns);
        }

        // Backward-compatible shorthand: `functions` on method/static nodes means any class + listed methods.
        if (null !== $legacyFunctions) {
            $patterns = [];
            foreach ($legacyFunctions as $methodName) {
                if (!is_string($methodName) || '' === $methodName) {
                    continue;
                }

                $patterns[] = new ForbiddenMethodPattern('*', $methodName);
            }

            return self::uniqueMethodPatterns($patterns);
        }

        return null; // no methods key means all method/static calls are forbidden for this node type
    }

    /**
     * @param list<ForbiddenMethodPattern> $patterns
     * @return list<ForbiddenMethodPattern>
     */
    private static function uniqueMethodPatterns(array $patterns): array
    {
        $unique = [];
        $seen   = [];

        foreach ($patterns as $pattern) {
            $key = $pattern->uniqueKey();
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[]   = $pattern;
        }

        return $unique;
    }

    /**
     * @param list<ForbiddenClassPattern> $patterns
     * @return list<ForbiddenClassPattern>
     */
    private static function uniqueClassPatterns(array $patterns): array
    {
        $unique = [];
        $seen   = [];

        foreach ($patterns as $pattern) {
            $key = $pattern->uniqueKey();
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[]   = $pattern;
        }

        return $unique;
    }

    private static function supportsMethodPatterns(NodeType $nodeType): bool
    {
        return NodeType::NODE_EXPR_METHOD_CALL === $nodeType || NodeType::NODE_EXPR_STATIC_CALL === $nodeType;
    }

    private static function supportsClassPatterns(NodeType $nodeType): bool
    {
        return NodeType::NODE_EXPR_NEW === $nodeType;
    }

    private function inferMatchesAll(): bool
    {
        if (self::supportsMethodPatterns($this->nodeType)) {
            return null === $this->methods;
        }

        if (self::supportsClassPatterns($this->nodeType)) {
            return null === $this->classes;
        }

        if (NodeType::NODE_EXPR_FUNC_CALL === $this->nodeType) {
            return null === $this->functions;
        }

        return true;
    }

    /**
     * @param array<string,mixed> $raw
     * @return list<string>
     */
    private static function readPaths(array $raw, string $snakeCaseKey, string $camelCaseKey): array
    {
        $paths = $raw[$snakeCaseKey] ?? $raw[$camelCaseKey] ?? [];
        if (!is_array($paths)) {
            return [];
        }

        $normalized = [];
        foreach ($paths as $path) {
            if (!is_string($path) || '' === $path) {
                continue;
            }

            $normalized[] = $path;
        }

        return $normalized;
    }
}
