<?php

declare(strict_types=1);

namespace TorfsICT\Bundle\CodeMonitoringBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TorfsICT\Bundle\CodeMonitoringBundle\ApiWriter\ApiWriter;

final class SendSpoolCommand extends Command
{
    use LockableTrait;

    public function __construct(private readonly ApiWriter $writer, ?string $name = null)
    {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('torfs-io:monitoring:spool:send');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->lock()) {
            $output->writeln('The command is already running in another process.');

            return Command::SUCCESS;
        }

        $this->writer->sendSpool();

        $this->release();

        return self::SUCCESS;
    }
}
