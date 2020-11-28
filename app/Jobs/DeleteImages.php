<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DeleteImages implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var string */
    private $uuid;

    /**
     * @param string $uuid The uuid of the job. The images are in a folder with {uuid} as name
     */
    public function __construct(string $uuid)
    {
        $this->uuid = $uuid;
    }

    /**
     * Delete the folder named {uuid} from the storage, including its content
     * Delete the expiry and job done keys from Redis
     *
     * @return void
     */
    public function handle(): void
    {
        \Storage::disk('shared')->deleteDirectory($this->uuid);
        \Redis::del('image-exp-' . $this->uuid);
        \Redis::del('image-done-' . $this->uuid);
    }
}
