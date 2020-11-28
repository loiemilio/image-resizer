<?php

namespace App\Console\Commands;

use App\Jobs\DeleteImages;
use Illuminate\Console\Command;

use Illuminate\Support\Facades\Redis;

class FlushImages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'images:flush';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete all expired images';

    private $keyPrefix = 'image-exp-';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // For every existing Redis record of image expiring time
        collect(Redis::keys('*image-exp-*'))->each(function ($key) {
            // Get the uuid and shorten key
            $uuid = \Str::after($key, $this->keyPrefix);
            $key = vsprintf('%s%s', [
                $this->keyPrefix,
                \Str::after($key, $this->keyPrefix),
            ]);

            // If the images expired delete them
            DeleteImages::dispatchIf(now()->isAfter(Redis::get($key)), $uuid);
        });

        return 0;
    }
}
