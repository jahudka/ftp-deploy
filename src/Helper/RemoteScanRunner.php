<?php

declare(strict_types=1);

namespace App\Helper;


class RemoteScanRunner
{
    private RemoteScannerBuilder $builder;
    private HelperRunner $helperRunner;

    public function __construct(RemoteScannerBuilder $builder, HelperRunner $helperRunner)
    {
        $this->builder = $builder;
        $this->helperRunner = $helperRunner;
    }

    public function scan() : array
    {
        $response = $this->helperRunner->run([$this->builder, 'build']);
        $list = explode("\n", $response);
        $rootDir = null;
        $files = [];

        foreach ($list as $line) {
            if ($record = $this->parseRecord($line)) {
                if ($record['type'] === 'rootDir') {
                    $rootDir = $record['path'];
                } else {
                    $files[$record['path']] = $record;
                }
            }
        }

        if (!isset($rootDir)) {
            throw new \Exception('Remote root dir not returned with scan');
        }

        return ['rootDir' => $rootDir, 'files' => $files];
    }


    private function parseRecord(string $line): array | null
    {
        if (!$line || !preg_match_all("~(?:^|:)(?|'((?:\\\\.|[^'\n\\\\])*)'|([^:\n]*))~", $line, $matches)) {
            return null;
        }

        $tokens = array_map(static fn (string $v) => strtr($v, ["\\'" => "'", '\\n' => "\n", '\\\\' => '\\']), $matches[1]);

        return match ($tokens[0]) {
            'R' => ['type' => 'rootDir', 'path' => $tokens[1]],
            'D' => ['type' => 'dir', 'mode' => (int) $tokens[1], 'path' => $tokens[2]],
            'F' => ['type' => 'file', 'mode' => (int) $tokens[1], 'hash' => $tokens[2], 'path' => $tokens[3]],
            'L' => ['type' => 'link', 'target' => $tokens[1], 'path' => $tokens[2]],
            '?' => ['type' => 'unknown', 'path' => $tokens[1]],
            default => throw new \Exception(sprintf('Unknown token: "%s"', $tokens[0])),
        };
    }
}
