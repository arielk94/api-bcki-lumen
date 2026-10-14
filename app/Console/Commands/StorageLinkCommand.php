<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class StorageLinkCommand extends Command
{
    protected $signature = 'storage:link';
    protected $description = 'Create a symbolic link from "public/storage" to "storage/app/public"';

    public function handle()
    {
        $target = storage_path('app/public');
        $link = app()->basePath('public/storage');

        if (file_exists($link)) {
            return $this->error('The "public/storage" directory already exists.');
        }

        app(Filesystem::class)->link($target, $link);
        $this->info('The [public/storage] directory has been linked.');
    }
}
