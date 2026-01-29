<?php

declare(strict_types=1);

namespace App\Infrastructure\Command;

use App\Application\Service\EventLoaderService;
use App\Domain\Service\LoaderCoordinationInterface;
use App\Domain\Service\RateLimiterInterface;
use App\Infrastructure\Service\HttpEventSource;
use App\Infrastructure\Service\InMemoryRateLimiter;
use App\Infrastructure\Service\RedisLoaderCoordination;
use App\Infrastructure\Service\RedisRateLimiter;
use App\Application\Query\GetEventsQueryHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * how to
 *   php bin/console app:event-loader
 *   php bin/console app:event-loader --sources=3 --iterations=10
 */
#[AsCommand(
    name: 'app:event-loader',
    description: 'Run event loader in infinite loop with round-robin'
)]
final class EventLoaderCommand extends Command
{
    public function __construct(
        private readonly GetEventsQueryHandler $queryHandler,
        private readonly LoggerInterface $logger,
        private readonly ?\Predis\ClientInterface $redis = null
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'sources',
                's',
                InputOption::VALUE_REQUIRED,
                'Number of event sources to create',
                3
            )
            ->addOption(
                'iterations',
                'i',
                InputOption::VALUE_REQUIRED,
                'Max iterations (0 = infinite loop)',
                0
            )
            ->addOption(
                'verbose-logs',
                null,
                InputOption::VALUE_NONE,
                'Enable verbose logging'
            )
            ->addOption(
                'distributed',
                'd',
                InputOption::VALUE_NONE,
                'Enable distributed coordination (requires Redis)'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $numSources = (int) $input->getOption('sources');
        $maxIterations = (int) $input->getOption('iterations');
        $useDistributed = $input->getOption('distributed');

        $this->displayConfiguration($io, $numSources, $maxIterations, $useDistributed);
        
        [$rateLimiter, $coordination] = $this->setupRateLimiterAndCoordination($io, $useDistributed);
        $loader = $this->createLoader($io, $rateLimiter, $coordination);
        $this->registerSources($io, $loader, $numSources);
        $this->registerSignalHandler($loader, $io);

        return $this->runLoader($io, $loader, $maxIterations);
    }

    private function displayConfiguration(SymfonyStyle $io, int $numSources, int $maxIterations, bool $useDistributed): void
    {
        $io->title('Event Loader Service');
        $io->section('Configuration');
        $io->table(
            ['Setting', 'Value'],
            [
                ['Event Sources', $numSources],
                ['Max Iterations', $maxIterations === 0 ? '∞ (infinite)' : $maxIterations],
                ['Rate Limit', '200ms per source'],
                ['Max Events/Request', '1000'],
                ['Distributed Mode', $useDistributed ? '✅ Enabled' : '❌ Disabled'],
            ]
        );
    }

    private function setupRateLimiterAndCoordination(SymfonyStyle $io, bool $useDistributed): array
    {
        if (!$useDistributed || $this->redis === null) {
            if ($useDistributed) {
                $io->warning('Redis client not available. Falling back to in-memory mode.');
            }
            return [new InMemoryRateLimiter(), null];
        }

        try {
            $this->redis->ping();
            $rateLimiter = new RedisRateLimiter($this->redis);
            $io->info('Using distributed rate limiter (Redis)');

            $instanceId = $_ENV['LOADER_INSTANCE_ID'] ?? null;
            if (empty($instanceId) || $instanceId === 'auto') {
                $instanceId = gethostname() . '-' . getmypid();
            }

            $coordination = new RedisLoaderCoordination($this->redis, $instanceId);
            $io->info("Instance ID: <info>$instanceId</info>");
            $io->info('Using distributed coordination (Redis)');

            return [$rateLimiter, $coordination];
        } catch (\Exception $e) {
            $io->warning('Redis connection failed: ' . $e->getMessage());
            $io->writeln('Falling back to in-memory mode.');
            return [new InMemoryRateLimiter(), null];
        }
    }

    private function createLoader(SymfonyStyle $io, RateLimiterInterface $rateLimiter, ?LoaderCoordinationInterface $coordination): EventLoaderService
    {
        $loader = new EventLoaderService($rateLimiter, $this->logger, $coordination);
        $loader->setConsoleOutput($io);
        return $loader;
    }

    private function registerSources(SymfonyStyle $io, EventLoaderService $loader, int $numSources): void
    {
        $io->section('Registering Event Sources');
        for ($i = 1; $i <= $numSources; $i++) {
            $sourceName = "event-source-$i";
            $source = new HttpEventSource($this->queryHandler, $sourceName);
            $loader->addSource($source);
            $io->writeln("Registered: <info>$sourceName</info>");
        }
    }

    private function runLoader(SymfonyStyle $io, EventLoaderService $loader, int $maxIterations): int
    {
        $io->success('Event Loader configured successfully!');
        $io->section('Starting Event Loader...');

        if ($maxIterations === 0) {
            $io->warning('Running in INFINITE mode. Press Ctrl+C to stop.');
        } else {
            $io->info("Running for $maxIterations iterations...");
        }

        try {
            $loader->start($maxIterations);
            $io->success('Event Loader stopped successfully!');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Event Loader failed: ' . $e->getMessage());
            $this->logger->error('Event loader command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return Command::FAILURE;
        }
    }

    private function registerSignalHandler(EventLoaderService $loader, SymfonyStyle $io): void
    {
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGINT, function () use ($loader, $io) {
                $io->newLine();
                $io->warning('Received interrupt signal (Ctrl+C)');
                $io->writeln('Stopping event loader gracefully...');
                $loader->stop();
            });
        }
    }
}
