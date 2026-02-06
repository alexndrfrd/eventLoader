# SCRUM-47: Console Command

**Priority:** Medium | **Points:** 2 | **Epic:** SCRUM-36

## Story
Implement Symfony console command to run the event loader.

## Implementation
```php
class EventLoaderCommand extends Command {
    protected static $defaultName = 'app:event-loader';

    public function __construct(private EventLoaderInterface $loader) {
        parent::__construct();
    }

    protected function configure(): void {
        $this
            ->setDescription('Run the event loader')
            ->addOption('sources', null, InputOption::VALUE_REQUIRED, 'Number of sources', 3)
            ->addOption('iterations', null, InputOption::VALUE_REQUIRED, 'Max iterations (0=infinite)', 0)
            ->addOption('distributed', null, InputOption::VALUE_NONE, 'Enable distributed mode')
            ->addOption('verbose-logs', null, InputOption::VALUE_NONE, 'Verbose logging');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $output->writeln('<info>Starting event loader...</info>');

        // Graceful shutdown
        pcntl_signal(SIGTERM, fn() => $this->loader->stop());
        pcntl_signal(SIGINT, fn() => $this->loader->stop());

        try {
            $this->loader->load();
            $output->writeln('<info>Event loader stopped</info>');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>Error: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}
```

## Usage
```bash
# Basic
php bin/console app:event-loader

# Distributed mode with 5 sources, 20 iterations
php bin/console app:event-loader --distributed --sources=5 --iterations=20

# Infinite loop with verbose logs
php bin/console app:event-loader --distributed --verbose-logs
```

## Options
- `--sources=N`: Number of event sources (default: 3)
- `--iterations=N`: Max iterations, 0 for infinite (default: 0)
- `--distributed`: Enable distributed mode with Redis
- `--verbose-logs`: Enable detailed logging

## Tests
- Command execution
- Options parsing
- Signal handling (SIGTERM, SIGINT)
- Exit codes (0=success, 1=failure)

## Links
Jira: https://alexandrubesleaga92.atlassian.net/browse/SCRUM-47
