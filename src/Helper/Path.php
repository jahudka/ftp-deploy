<?php

declare(strict_types=1);

namespace App\Helper;


class Path
{
    public static function isAbsolute(string $path) : bool
    {
        return str_starts_with($path, '/');
    }

    public static function isSubpathOf(string $path, string $parent) : bool
    {
        return str_starts_with(sprintf('%s/', rtrim($path, '/')), sprintf('%s/', rtrim($parent, '/')));
    }

    public static function resolve(string ...$paths) : string
    {
        $path = '';

        for ($i = count($paths) - 1; $i >= 0; --$i) {
            if (self::isAbsolute($paths[$i])) {
                return self::normalize(sprintf('%s%s', rtrim($paths[$i], '/'), $path) ?: '/');
            } else {
                $path = sprintf('/%s%s', rtrim($paths[$i], '/'), $path);
            }
        }

        return self::normalize(sprintf('%s%s', getcwd(), $path));
    }

    public static function normalize(string $path): string
    {
        $path = preg_replace('~/\.(?=/|$)~', '', $path);

        while (preg_match('~^(.*?)/[^/]+/\.\.(/.*)?$~', $path, $m)) {
            $path = $m[1] . ($m[2] ?? '');
        }

        return $path;
    }

    public static function relative(string $from, string $to): string
    {
        $from = explode('/', trim(self::normalize($from), '/'));
        $to = explode('/', trim(self::normalize($to), '/'));
        $i = 0;
        $n = min(count($from), count($to));

        while ($i < $n && $from[$i] === $to[$i]) {
            ++$i;
        }

        return implode('/', [
            ...array_fill(0, count($from) - $i, '..'),
            ...array_slice($to, $i),
        ]);
    }
}
