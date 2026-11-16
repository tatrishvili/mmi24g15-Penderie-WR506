<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\ActivityLog;
use App\Entity\PageVisit;
use App\Entity\PerformanceMetric;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:purge-analytics',
    description: 'Delete analytics, performance metrics and audit entries older than the retention window.',
)]
final class PurgeAnalyticsCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('days', null, InputOption::VALUE_REQUIRED, 'Retention window in days', '180');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = max(1, (int) $input->getOption('days'));
        $cutoff = new \DateTimeImmutable(sprintf('-%d days', $days));

        $visits = $this->em->createQuery('DELETE FROM '.PageVisit::class.' v WHERE v.createdAt < :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->execute();
        $activity = $this->em->createQuery('DELETE FROM '.ActivityLog::class.' a WHERE a.createdAt < :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->execute();
        $performance = $this->em->createQuery('DELETE FROM '.PerformanceMetric::class.' m WHERE m.bucketDate < :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->execute();

        $io->success(sprintf(
            'Purged %d visits, %d activity entries and %d performance buckets older than %d days.',
            $visits,
            $activity,
            $performance,
            $days,
        ));

        return Command::SUCCESS;
    }
}
