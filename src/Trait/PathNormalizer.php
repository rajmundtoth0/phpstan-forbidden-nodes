<?php

declare(strict_types=1);

namespace rajmundtoth0\PHPStanForbidden\Trait;

trait PathNormalizer
{
    /**
     * Normalize configured paths to use forward slashes and no trailing slash,
     * preserving any wildcard characters so they can be matched with fnmatch.
     *
     * @param array<mixed> $paths
     * @return list<string>
     */
    public function normalizePaths(array $paths): array
    {
        $normalized = [];

        foreach ($paths as $path) {
            if (!is_string($path) || '' === $path) {
                continue;
            }

            $normalized[] = rtrim(str_replace('\\', '/', $path), '/');
        }

        return array_values(array_unique($normalized));
    }

    /**
     * Match an analysed file path against a configured path.
     *
     * A plain path matches on directory-segment boundaries, so `/app` matches
     * `.../app/Foo.php` but not `.../myapp/Foo.php`. A path containing a
     * wildcard (asterisk, question mark or bracket) is matched with fnmatch
     * against the whole file path, and an asterisk also crosses directory
     * separators, so a user can scope precisely with a wildcard around a
     * generated directory or with an absolute prefix.
     */
    public function matchesPath(string $file, string $needle): bool
    {
        if ('' === $needle) {
            return false;
        }

        $file = str_replace('\\', '/', $file);

        if (false !== strpbrk($needle, '*?[')) {
            return fnmatch($needle, $file);
        }

        // Segment-boundary match: wrap both sides in slashes so the needle only
        // matches whole path segments, never a substring of a longer segment.
        $needle = '/'.trim($needle, '/').'/';

        return str_contains(rtrim($file, '/').'/', $needle);
    }
}
