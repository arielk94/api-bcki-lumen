<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class ServeCommand extends Command
{
    protected $signature = 'serve {--host=127.0.0.1} {--port=8000} {--swagger}';
    protected $description = 'Serve the Lumen application on the PHP development server';

    public function handle()
    {
        $host = $this->option('host');
        $port = $this->option('port');
        $swagger = $this->option('swagger');
        $publicPath = base_path('public');

        $this->info("Starting Lumen development server at http://$host:$port");

        $command = $swagger ? ["php", "-S", "$host:$port", "$publicPath/index.php"] : ["php", "-S", "$host:$port", "-t", $publicPath];

        $process = new Process($command);
        $process->setTimeout(null); // ⛔ Jangan timeout
        $process->start(); // ✅ Jalankan tanpa blocking

        // Jaga agar command Lumen tidak langsung selesai
        while ($process->isRunning()) {
            sleep(1);
        }
    }
}