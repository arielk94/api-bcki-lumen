<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class KeyGenerateCommand extends Command
{
    protected $signature = 'key:generate';
    protected $description = 'Set the application key';

    public function handle()
    {
        $key = 'base64:' . base64_encode(random_bytes(32));
        
        $path = base_path('.env');

        if (!file_exists($path)) {
            $this->error('.env file not found');
            return;
        }

        file_put_contents($path, preg_replace(
            '/^APP_KEY=.*$/m',
            "APP_KEY=$key",
            file_get_contents($path)
        ));

        $this->info("Application key set successfully: $key");
    }
}
