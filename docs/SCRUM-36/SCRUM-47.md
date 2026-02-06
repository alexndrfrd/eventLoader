# SCRUM-47: Console Command

**Priority:** Medium | **Points:** 2

Implement Symfony console command to run the event loader.

## Implementation

```php
class EventLoaderCommand extends Command
{
    protected static $defaultName = 'app:event-loader';

    public function __construct(
        private EventLoaderInterface $loader
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Run the event loader')
            ->addOption('sources', null, InputOption::VALUE_REQUIRED, 'Number of sources', 3)
            ->addOption('iterations', null, InputOption::VALUE_REQUIRED, 'Max iterations (0=infinite)', 0)
            ->addOption('distributed', null, InputOption::VALUE_NONE, 'Enable distributed mode')
            ->addOption('verbose-logs', null, InputOption::VALUE_NONE, 'Enable verbose logging');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Starting event loader...</info>');

        // Register signal handlers for graceful shutdown
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
php bin/console app:event-loader --distributed --iterations=20
```

## Tests
- Command execution
- Options parsing
- Signal handling
- Error exit codes
