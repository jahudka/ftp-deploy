<?php

declare(strict_types=1);

namespace App\Helper;

use Symfony\Component\Console\Output\OutputInterface;


class DeployRunner
{
    private RemoteScanRunner $remoteScanner;
    private Comparator $comparator;
    private ArchiveBuilder $archiveBuilder;
    private HelperRunner $helperRunner;

    public function __construct(
        RemoteScanRunner $remoteScanner,
        Comparator $comparator,
        ArchiveBuilder $archiveBuilder,
        HelperRunner $helperRunner
    ) {
        $this->remoteScanner = $remoteScanner;
        $this->comparator = $comparator;
        $this->archiveBuilder = $archiveBuilder;
        $this->helperRunner = $helperRunner;
    }


    public function run(OutputInterface $output, bool $dryRun = false) : bool
    {
        $output->writeln('Scanning remote files...', OutputInterface::VERBOSITY_VERBOSE);
        $remote = $this->remoteScanner->scan();
        $output->writeln(sprintf('Remote root: %s', $remote['rootDir']), OutputInterface::VERBOSITY_VERY_VERBOSE);
        $hasError = false;

        foreach ($this->comparator->compareWithLocal($remote['files'], $remote['rootDir']) as $path => $info) {
            $output->writeln(
                sprintf('%s %s', $info['action'], $path),
                $info['action'] === 'error' ? OutputInterface::VERBOSITY_QUIET : OutputInterface::VERBOSITY_NORMAL,
            );

            switch ($info['action']) {
                case 'mkdir':
                    $this->archiveBuilder->createDirectory($path, $info['mode']);
                    break;
                case 'chmod':
                    $this->archiveBuilder->changePermissions($path, $info['mode']);
                    break;
                case 'symlink':
                    $this->archiveBuilder->createSymlink($path, $info['target']);
                    break;
                case 'upload':
                    $this->archiveBuilder->uploadFile($info['path'], $path, $info['hash'], $info['mode']);
                    break;
                case 'rmdir':
                    $this->archiveBuilder->deleteDirectory($path);
                    break;
                case 'unlink':
                    $this->archiveBuilder->deleteFile($path);
                    break;
                case 'error':
                    $output->writeln(sprintf('  message: %s', $info['message']), OutputInterface::VERBOSITY_QUIET);
                    $hasError = true;
                    break;
            }
        }

        if (!$hasError && $this->archiveBuilder->isEmpty()) {
            $output->writeln('No actions required.');
        }

        if ($hasError) {
            return false;
        } else if ($dryRun || $this->archiveBuilder->isEmpty()) {
            return true;
        }

        $output->writeln('Deploying...', OutputInterface::VERBOSITY_VERBOSE);
        $response = $this->helperRunner->run([$this->archiveBuilder, 'build']);
        $hasError = (bool) preg_match('~^\[FAIL]~m', $response);

        if ($hasError || $output->isVeryVerbose()) {
            $output->writeln($response);
        }

        if ($hasError) {
            $output->writeln('Deploy failed.');
            return false;
        } else {
            $output->writeln('Deployed successfully.', OutputInterface::VERBOSITY_VERBOSE);
            return true;
        }
    }
}
