<?php

declare(strict_types=1);

namespace App\Helper;

use FilesystemIterator;
use Generator;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;


class LocalScanner
{
    private string $rootDir;
    private FileFilter $filter;

    public function __construct(string $rootDir, FileFilter $filter)
    {
        $this->rootDir = $rootDir;
        $this->filter = $filter;
    }

    /** @return Generator<string, array> */
    public function scan() : Generator
    {
        /** @var RecursiveDirectoryIterator[] $files */
        $files = new RecursiveIteratorIterator(
            new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator(
                    $this->rootDir,
                    FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_SELF,
                ),
                fn(RecursiveDirectoryIterator $file) => $this->filter->accepts($file->getSubPathname()),
            ),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($files as $file) {
            if ($file->isLink()) {
                yield $file->getSubPathname() => ['type' => 'link', 'target' => $file->getLinkTarget()];
            } else if ($file->isDir()) {
                yield $file->getSubPathname() => ['type' => 'dir', 'mode' => $file->getPerms() & 0777];
            } else if ($file->isFile()) {
                yield $file->getSubPathname() => [
                    'type' => 'file',
                    'mode' => $file->getPerms() & 0777,
                    'hash' => sha1_file($file->getPathname()),
                    'path' => $file->getPathname(),
                ];
            } else {
                yield $file->getSubPathname() => ['type' => 'unknown'];
            }
        }
    }

    public function getRootDir() : string
    {
        return $this->rootDir;
    }
}
