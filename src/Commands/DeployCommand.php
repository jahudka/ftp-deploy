<?php

declare(strict_types=1);

namespace App\Commands;


use App\Helper\Comparator;
use App\Helper\DeployRunner;
use App\Helper\RemoteScanRunner;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class DeployCommand extends Command
{
    private DeployRunner $deployRunner;

    public function __construct(DeployRunner $deployRunner)
    {
        parent::__construct();
        $this->deployRunner = $deployRunner;
    }


    protected function configure() : void
    {
        $this->setName('ftp-deploy')
            ->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Path to the config file')
            ->addOption('dry-run', 'd', InputOption::VALUE_NONE, 'Dry run');
    }

    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        $dry = (bool) $input->getOption('dry-run');
        return $this->deployRunner->run($output, $dry) ? 0 : 1;
    }
}
