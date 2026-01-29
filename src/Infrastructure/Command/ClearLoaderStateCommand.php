<?php

declare(strict_types=1);

namespace App\Infrastructure\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:clear-loader-state',
    description: 'Clear loader state from Redis (locks, last processed IDs, rate limits)'
)]
final class ClearLoaderStateCommand extends Command
{
    public function __construct(
        private readonly ?\Predis\ClientInterface $redis = null
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($this->redis === null) {
            $io->warning('Redis client not available. Nothing to clear.');
            return Command::SUCCESS;
        }

        try {
            $this->redis->ping();
        } catch (\Exception $e) {
            $io->error('Redis connection failed: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $io->title('Clear Loader State from Redis');

        $lockKeys = $this->redis->keys('loader_lock:*');
        $lastIdKeys = $this->redis->keys('last_processed_id:*');
        $rateLimitKeys = $this->redis->keys('rate_limit:*');
        $instanceKeys = $this->redis->keys('loader_instance:*');

        $totalKeys = count($lockKeys) + count($lastIdKeys) + count($rateLimitKeys) + count($instanceKeys);

        if ($totalKeys === 0) {
            $io->info('No loader state found in Redis. Nothing to clear.');
            return Command::SUCCESS;
        }

        $io->section('Found Keys');
        $io->table(
            ['Type', 'Count'],
            [
                ['Locks', count($lockKeys)],
                ['Last Processed IDs', count($lastIdKeys)],
                ['Rate Limits', count($rateLimitKeys)],
                ['Instance IDs', count($instanceKeys)],
                ['Total', $totalKeys],
            ]
        );

        // Delete all keys
        $allKeys = array_merge($lockKeys, $lastIdKeys, $rateLimitKeys, $instanceKeys);
        
        if (!empty($allKeys)) {
            $this->redis->del($allKeys);
        }

        $io->success("Cleared $totalKeys keys from Redis!");

        return Command::SUCCESS;
    }
}
