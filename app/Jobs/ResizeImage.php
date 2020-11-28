<?php

namespace App\Jobs;

use App\Http\Requests\UploadImageRequest;
use GuzzleHttp\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;
use Intervention\Image\Image;

class ResizeImage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var int */
    private $allow = 100;
    /** @var int */
    private $every = 1;

    /** @var string */
    private $uuid;
    /** @var array|string[] */
    private $request;

    /** @var string */
    private $webhook;
    /** @var Client */
    private $client;

    public function __construct(string $uuid, UploadImageRequest $request)
    {
        $this->uuid = $uuid;

        $this->request = $request->input();
        $this->webhook = $request->input('webhook');

        \Storage::disk('shared')->put(
            $this->uuid,
            $request->file('image'),
            '',
        );
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Redis::throttle(class_basename($this))
            ->allow($this->allow)
            ->every($this->every)
            ->then(function () {
                if (Redis::set('image-' . $this->uuid, getmypid(), 'EX', 10 * 60, 'NX')) {
                    $this->process();
                    Redis::del('image-' . $this->uuid);
                } else {
                    $this->release($this->allow);
                }
            }, function () {
                $this->release($this->allow);
            });
    }

    private function process()
    {
        array_map(function (string $path) {
            try {
                $image = \Image::make(\Storage::disk('shared')->get($path))->resize(100, 100);

                if ($this->webhook) {
                    $this->sendImage($image);
                } else {
                    Redis::set('image-exp-' . $this->uuid, \Carbon\Carbon::parse('+1 hour'));
                    \Storage::disk('shared')->put($path, $image->stream());
                }
            } finally {
//                \Storage::disk('shared')->delete($path);
            }
        }, \Storage::disk('shared')->files($this->uuid, false));
    }

    private function client()
    {
        if (!$this->client) {
            $this->client = new Client([
                'verify' => false,
            ]);
        }

        return $this->client;
    }

    private function sendImage(Image $image)
    {
        $request = $this->client()->postAsync($this->webhook, [
            'form_params' => [
                'uuid' => $this->uuid,
                'image' => $image->stream(),
            ],
        ]);

        $request->wait();

        dump(['return' => $request]);
    }
}
