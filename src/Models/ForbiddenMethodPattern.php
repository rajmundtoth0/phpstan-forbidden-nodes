<?php

declare(strict_types=1);

namespace rajmundtoth0\PHPStanForbidden\Models;

/**
 * @internal
 */
final class ForbiddenMethodPattern
{
    public readonly string $classPattern;

    public readonly string $methodPattern;

    public function __construct(string $classPattern, string $methodPattern)
    {
        $this->classPattern  = self::normalizeClassName($classPattern);
        $this->methodPattern = strtolower($methodPattern);
    }

    public function matches(?string $className, ?string $methodName): bool
    {
        if (null === $methodName || '' === $methodName) {
            return false;
        }

        if (!self::matchesPattern($this->methodPattern, strtolower($methodName))) {
            return false;
        }

        if ('*' === $this->classPattern) {
            return true;
        }

        if (null === $className || '' === $className) {
            return false;
        }

        return self::matchesPattern($this->classPattern, self::normalizeClassName($className));
    }

    public function uniqueKey(): string
    {
        return $this->classPattern.'::'.$this->methodPattern;
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
