<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class PollDeviceEvents extends Command
{
    protected $signature = 'events:poll';

    protected $description = 'Poll device/interface events and store them in the database.';

    public function handle(): int
    {
        $scriptPath = base_path('scripts/poller.php');
        if (!is_file($scriptPath)) {
            $message = 'poller.php not found in scripts folder.';
            $this->error($message);
            Log::warning('events:poll failed: script missing', [
                'path' => $scriptPath,
            ]);
            return self::FAILURE;
        }

        $phpBinary = PHP_BINARY ?: 'php';
        $process = new Process([$phpBinary, $scriptPath], base_path());
        $process->setTimeout(180);
        $process->run();

        $output = trim($process->getOutput() . "\n" . $process->getErrorOutput());
        if (!$process->isSuccessful()) {
            Log::warning('events:poll execution failed', [
                'exit_code' => $process->getExitCode(),
                'output' => $output,
            ]);
            $this->error('events:poll execution failed.');
            if ($output !== '') {
                $this->line($output);
            }
            return self::FAILURE;
        }

        if ($output !== '') {
            $this->line($output);
        }

        $this->info('events:poll completed.');
        return self::SUCCESS;
    }
}

