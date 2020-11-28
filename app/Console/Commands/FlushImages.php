<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use Illuminate\Support\Facades\Redis;

class FlushImages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'images:flush {--time=1 minute ago}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deletes images older than `--time`';

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
        collect(Redis::keys('*image-exp-*'))->each(function ($key) {
            $key = vsprintf('%s%s', [
                $this->keyPrefix,
                \Str::after($key, $this->keyPrefix),
            ]);
            if (now()->isAfter(Redis::get($key))) {
                $uuid = \Str::after($key, $this->keyPrefix);
                \Storage::disk('shared')->deleteDirectory($uuid);
                Redis::del($key);
                Redis::del('image-done-' . $uuid);
            }
        });

        return 0;
    }
}
