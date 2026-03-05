<?php

namespace rajmundtoth0\PHPStanForbidden\Trait;

trait PathNormalizer
{
    /**
     * @param list<string> $paths
     * @return list<string>
     */
    public function normalizePaths(array $paths): array
    {
        $normalized = [];
        foreach ($paths as $path) {
            if (!is_string($path) || $path === '') {
                continue;
            }
            $normalized[] = rtrim(str_replace('\\', '/', $path), '/') . '/';
        }
        return array_values(array_unique($normalized));
    }
}