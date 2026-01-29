<?php

declare(strict_types=1);

namespace App\Infrastructure\Command;

use App\Application\Service\EventLoaderService;
use App\Domain\Entity\Event;
use App\Domain\Repository\EventRepositoryInterface;
use App\Domain\Service\LoaderCoordinationInterface;
use App\Domain\Service\RateLimiterInterface;
use App\Domain\ValueObject\EventDate;
use App\Domain\ValueObject\EventId;
use App\Domain\ValueObject\EventName;
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

#[AsCommand(
    name: 'app:loader-with-data',
    description: 'Create test events and run loader (events persist in same process)'
)]
final class LoaderWithTestDataCommand extends Command
{
    public function __construct(
        private readonly GetEventsQueryHandler $queryHandler,
        private readonly EventRepositoryInterface $eventRepository,
        private readonly LoggerInterface $logger,
        private readonly ?\Predis\ClientInterface $redis = null
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'events',
                null,
                InputOption::VALUE_REQUIRED,
                'Number of test events to create',
                200
            )
            ->addOption(
                'sources',
                's',
                InputOption::VALUE_REQUIRED,
                'Number of event sources',
                3
            )
            ->addOption(
                'iterations',
                'i',
                InputOption::VALUE_REQUIRED,
                'Max iterations (0 = infinite)',
                20
            )
            ->addOption(
                'distributed',
                'd',
                InputOption::VALUE_NONE,
                'Enable distributed coordination'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $numEvents = (int) $input->getOption('events');
        $numSources = (int) $input->getOption('sources');
        $maxIterations = (int) $input->getOption('iterations');
        $useDistributed = $input->getOption('distributed');

        $io->title('Event Loader with Test Data');

        $io->section('Creating Test Events');
        $io->info("Creating $numEvents test events...");

        $created = 0;
        for ($i = 1; $i <= $numEvents; $i++) {
            $eventId = $this->eventRepository->nextId();
            $event = Event::create(
                $eventId,
                EventName::fromString("Test Event $i"),
                EventDate::fromString('2026-12-31')
            );
            
            $this->eventRepository->save($event);
            $created++;
        }

        $io->success("Created $created test events!");
        $io->table(
            ['Property', 'Value'],
            [
                ['Total Events', $created],
                ['First Event ID', '1'],
                ['Last Event ID', (string) $created],
            ]
        );

        $io->section('Setting Up Loader');

        // Create rate limiter
        if ($useDistributed && $this->redis !== null) {
            try {
                $this->redis->ping();
                $rateLimiter = new RedisRateLimiter($this->redis);
                $io->info('Using distributed rate limiter (Redis)');
                
                $instanceId = $_ENV['LOADER_INSTANCE_ID'] ?? gethostname() . '-' . getmypid();
                $coordination = new RedisLoaderCoordination($this->redis, $instanceId);
                $io->info("Instance ID: <info>$instanceId</info>");
            } catch (\Exception $e) {
                $io->warning('Redis failed, using in-memory');
                $rateLimiter = new InMemoryRateLimiter();
                $coordination = null;
            }
        } else {
            $rateLimiter = new InMemoryRateLimiter();
            $coordination = null;
        }

        $loader = new EventLoaderService($rateLimiter, $this->logger, $coordination);
        $loader->setConsoleOutput($io);

        for ($i = 1; $i <= $numSources; $i++) {
            $sourceName = "event-source-$i";
            $source = new HttpEventSource($this->queryHandler, $sourceName);
            $loader->addSource($source);
        }

        $io->section('Starting Loader');
        
        if ($maxIterations === 0) {
            $io->warning('Running in INFINITE mode. Press Ctrl+C to stop.');
        } else {
            $io->info("Running for $maxIterations iterations...");
        }

        try {
            $loader->start($maxIterations);
            $io->success('Loader stopped successfully!');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Loader failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
