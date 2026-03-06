<?php

declare(strict_types=1);

namespace rajmundtoth0\PHPStanForbidden\Models;

use rajmundtoth0\PHPStanForbidden\Trait\PathNormalizer;

/**
 * @internal
 */
final class ForbiddenNodes
{
    use PathNormalizer;

    /** @var array<string,list<ForbiddenNode>> */
    private readonly array $nodesByType;

    /** @var list<string> */
    public readonly array $includePaths;

    /** @var list<string> */
    public readonly array $excludePaths;

    /**
     * @param list<ForbiddenNode> $nodes
     * @param list<string> $includePaths
     * @param list<string> $excludePaths
     */
    public function __construct(
        public readonly array $nodes,
        public readonly bool $useFromTests,
        public readonly bool $forbidDynamicFunctionCalls,
        public readonly bool $nonIgnorable,
        array $includePaths = [],
        array $excludePaths = [],
    ) {
        $this->includePaths = $this->normalizePaths($includePaths);
        $this->excludePaths = $this->normalizePaths($excludePaths);
        $this->nodesByType  = $this->groupNodesByType($this->nodes);
    }

    /**
     * @param array<string,mixed> $raw
     */
    public static function fromArray(array $raw): self
    {
        $nodes    = [];
        $rawNodes = $raw['nodes'] ?? [];

        if (!is_array($rawNodes)) {
            $rawNodes = [];
        }

        foreach ($rawNodes as $rawNode) {
            if (!is_array($rawNode)) {
                continue;
            }

            $normalizedRawNode = [];
            foreach ($rawNode as $key => $value) {
                if (!is_string($key)) {
                    continue;
                }

                $normalizedRawNode[$key] = $value;
            }

            $node = ForbiddenNode::fromArray($normalizedRawNode);
            if (null === $node) {
                continue;
            }

            $nodes[] = $node;
        }

        return new self(
            nodes: $nodes,
            useFromTests: (bool) ($raw['use_from_tests'] ?? $raw['useFromTests'] ?? true),
            forbidDynamicFunctionCalls: (bool) ($raw['forbid_dynamic_function_calls'] ?? $raw['forbidDynamicFunctionCalls'] ?? false),
            nonIgnorable: (bool) ($raw['non_ignorable'] ?? $raw['nonIgnorable'] ?? true),
            includePaths: self::readPaths($raw, 'include_paths', 'includePaths'),
            excludePaths: self::readPaths($raw, 'exclude_paths', 'excludePaths'),
        );
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

    public function hasNodeType(string $nodeType): bool
    {
        return isset($this->nodesByType[$nodeType]);
    }

    /**
     * @return list<ForbiddenNode>
     */
    public function nodesForType(string $nodeType): array
    {
        return $this->nodesByType[$nodeType] ?? [];
    }

    /**
     * @param list<ForbiddenNode> $nodes
     * @return array<string,list<ForbiddenNode>>
     */
    private function groupNodesByType(array $nodes): array
    {
        $grouped = [];

        foreach ($nodes as $node) {
            $grouped[$node->nodeType->value] ??= [];
            $grouped[$node->nodeType->value][] = $node;
        }

        return $grouped;
    }
}
