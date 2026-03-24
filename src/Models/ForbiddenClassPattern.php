<?php

declare(strict_types=1);

namespace rajmundtoth0\PHPStanForbidden\Models;

/**
 * @internal
 */
final class ForbiddenClassPattern
{
    public readonly string $classPattern;

    public function __construct(string $classPattern)
    {
        $this->classPattern = self::normalizeClassName($classPattern);
    }

    public function matches(?string $className): bool
    {
        if (null === $className || '' === $className) {
            return false;
        }

        return self::matchesPattern($this->classPattern, self::normalizeClassName($className));
    }

    public function uniqueKey(): string
    {
        return $this->classPattern;
    }

    private static function normalizeClassName(string $className): string
    {
        return strtolower(ltrim($className, '\\'));
    }

    private static function matchesPattern(string $pattern, string $value): bool
    {
        if ('*' === $pattern) {
            return true;
        }

        $regex = '~^'.str_replace('\*', '.*', preg_quote($pattern, '~')).'$~';

        return 1 === preg_match($regex, $value);
    }
}
