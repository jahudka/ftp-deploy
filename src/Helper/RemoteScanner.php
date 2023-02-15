<?php

declare(strict_types=1);

namespace App\Helper;

use FilesystemIterator;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;


class RemoteScanner
{
    private string $rootDir;
    private array $patterns;

    public function __construct(string $rootDir, string $key, array $patterns)
    {
        if (!isset($_POST['key']) || $_POST['key'] !== $key) {
            unlink(__FILE__);
            echo "[FAIL] invalid key\n";
            exit;
        }

        $this->rootDir = realpath(sprintf('%s/%s', __DIR__, trim($rootDir, '/') ?: '.'));
        $this->patterns = $patterns;

        set_time_limit(0);
    }


    public function scan(): void
    {
        printf("R:'%s'\n", $this->escapePath($this->rootDir));

        /** @var RecursiveDirectoryIterator[] $files */
        $files = new RecursiveIteratorIterator(
            new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator(
                    $this->rootDir,
                    FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_SELF,
                ),
                fn($file) => $this->accepts($file),
            ),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($files as $file) {
            if ($file->getPathname() === __FILE__) {
                continue;
            } else if ($file->isLink()) {
                printf("L:'%s':'%s'\n", $this->escapePath($file->getLinkTarget()), $this->escapePath($file->getSubPathname()));
            } else if ($file->isDir()) {
                printf("D:%d:'%s'\n", $file->getPerms() & 0777, $this->escapePath($file->getSubPathname()));
            } else if ($file->isFile()) {
                printf("F:%d:%s:'%s'\n", $file->getPerms() & 0777, sha1_file($file->getPathname()), $this->escapePath($file->getSubPathname()));
            } else {
                printf("?:'%s'\n", $this->escapePath($file->getSubPathname()));
            }
        }
    }

    public function cleanup(): void {
        unlink(__FILE__);
    }

    private function accepts(RecursiveDirectoryIterator $file) : bool
    {
        $path = sprintf('/%s', trim($file->getSubPathname(), '/'));

        foreach ($this->patterns as $pattern => $result) {
            if (preg_match($pattern, $path)) {
                return $result;
            }
        }

        return true;
    }

    private function escapePath(string $path): string {
        return strtr($path, ["'" => "\\'", "\n" => '\\n', '\\' => '\\\\']);
    }
}
