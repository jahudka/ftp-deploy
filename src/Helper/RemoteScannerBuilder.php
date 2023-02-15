<?php

declare(strict_types=1);

namespace App\Helper;


class RemoteScannerBuilder
{
    private string $rootDir;
    private FileFilter $fileFilter;

    public function __construct(string $rootDir, FileFilter $fileFilter)
    {
        $this->rootDir = $rootDir;
        $this->fileFilter = $fileFilter;
    }

    public function build(string $scannerPath, string $key): void
    {
        $writer = new HelperWriter($scannerPath);
        $writer->writeFile(__DIR__ . '/RemoteScanner.php');
        $writer->writeln();
        $writer->writeln(
            '$scanner = new RemoteScanner(%s, %s, %s);',
            $this->rootDir,
            $key,
            $this->fileFilter->getRemotePatterns()
        );
        $writer->writeln('$scanner->scan();');
        $writer->writeln('$scanner->cleanup();');
        $writer->close();
    }
}
