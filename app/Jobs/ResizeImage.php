<?php

namespace App\Jobs;

use App\Http\AsynClient;
use App\Http\Requests\UploadImageRequest;
use GuzzleHttp\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Redis;

class ResizeImage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var int */
    private $allow = 100;
    /** @var int */
    private $every = 1;

    /** @var string */
    private $uuid;

    /** @var string */
    private $webhook;
    /** @var Client */
    private $client;

    public function __construct(string $uuid, UploadImageRequest $request)
    {
        $this->uuid = $uuid;
        $this->webhook = $request->input('webhook');

        if (!$files = $request->file('images')) {
            $files = [$request->file('image')];
        }

        collect($files)->each(function (UploadedFile $file) {
            \Storage::disk('shared')->put(
                vsprintf('%s/%s', [
                    $this->uuid,
                    $file->getClientOriginalName(),
                ]),
                file_get_contents($file),
            );
        });
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
        collect(\Storage::disk('shared')->files($this->uuid, false))
            ->mapWithKeys(function (string $path) {
                $image = \Image::make(\Storage::disk('shared')->get($path))->resize(100, 100);

                if ($this->webhook) {
                    return [$path => $image];
                }

                Redis::set('image-exp-' . $this->uuid, $expireTime = \Carbon\Carbon::parse('+1 hour'));
                \Storage::disk('shared')->put($path, $image->stream());

                return [$path => $expireTime];
            })->when($this->webhook, function (Collection $files) {
                $payload = [
                    'uuid' => $this->uuid,
                    'images' => $files->map(function ($image, $key) {
                        return [
                            'name' => \Str::after($key, $this->uuid . '/'),
                            'content' => urlencode($image->stream()),
                        ];
                    })->values()->all(),
                ];

                AsynClient::postJson($this->webhook, $payload);

                \Storage::disk('shared')->deleteDirectory($this->uuid);
            });
    }
}
