<?php

namespace App\Console\Commands;

use App\Support\ProvisioningTrace;
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
        ProvisioningTrace::log('events poll trace: command started', [
            'trace' => 'events polling',
            'trigger' => 'events:poll',
            'script_name' => 'poller.php',
            'script_path' => $scriptPath,
        ]);

        if (!is_file($scriptPath)) {
            $message = 'poller.php not found in scripts folder.';
            $this->error($message);
            Log::warning('events:poll failed: script missing', [
                'path' => $scriptPath,
            ]);
            ProvisioningTrace::log('events poll trace: script missing', [
                'trace' => 'events polling',
                'trigger' => 'events:poll',
                'script_name' => 'poller.php',
                'script_path' => $scriptPath,
            ]);
            return self::FAILURE;
        }

        $phpBinary = PHP_BINARY ?: 'php';
        $command = [$phpBinary, $scriptPath];
        $process = new Process($command, base_path());
        $process->setTimeout(180);
        $result = ProvisioningTrace::runProcess($process, [
            'label' => 'events poll trace',
            'line_prefix' => 'events poll trace',
            'context' => [
                'trace' => 'events polling',
                'trigger' => 'events:poll',
                'script_name' => 'poller.php',
                'script_path' => $scriptPath,
                'command' => $command,
            ],
        ]);

        if (!$result['ok']) {
            $output = $result['output'];
            Log::warning('events:poll execution failed', [
                'exit_code' => $result['exit_code'],
                'output' => $output,
            ]);
            $this->error('events:poll execution failed.');
            if ($output !== '') {
                $this->line($output);
            }
            return self::FAILURE;
        }

        $output = $result['output'];
        if ($output !== '') {
            $this->line($output);
        }

        $this->info('events:poll completed.');
        return self::SUCCESS;
    }
}
