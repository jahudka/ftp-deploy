<?php

declare(strict_types=1);

namespace App\Helper;


class FileFilter
{
    private array $localPatterns = [];
    private array $remotePatterns = [];

    public function __construct(array $patterns)
    {
        foreach ($patterns as $pattern) {
            $this->preparePattern($pattern);
        }
    }

    public function getRemotePatterns() : array
    {
        return $this->remotePatterns;
    }

    public function accepts(string $file) : bool
    {
        $file = sprintf('/%s', trim($file, '/'));

        foreach ($this->localPatterns as $pattern => $result) {
            if (preg_match($pattern, $file)) {
                return $result;
            }
        }

        return true;
    }

    private function preparePattern(string $pattern) : void
    {
        preg_match('~^(?:(local|remote):)?(!)?(/)?(.*?)/?$~', $pattern, $m); // will always match

        $locality = $m[1] ?? null;
        $result = empty($m[2]);
        $root = !empty($m[3]) ? '/' : '/(?:.+?/)?';
        $path = strtr(preg_quote($m[4]), ['\\*\\*' => '.*', '\\*' => '[^/]*']);
        $pattern = sprintf('~^%s%s(?:/|$)~', $root, $path);

        if ($locality !== 'remote') {
            $this->localPatterns[$pattern] = $result;
        }

        if ($locality !== 'local') {
            $this->remotePatterns[$pattern] = $result;
        }
    }
}
