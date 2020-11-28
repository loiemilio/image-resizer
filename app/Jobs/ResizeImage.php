<?php

namespace App\Jobs;

use App\Http\AsynClient;
use App\Http\Requests\UploadImageRequest;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Redis;
use Illuminate\Http\UploadedFile;

class ResizeImage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var int */
    private $allow;
    /** @var int */
    private $every;

    /** @var string */
    private $uuid;

    /** @var string */
    private $webhook;

    public function __construct(string $uuid, UploadImageRequest $request)
    {
        $this->allow = config('resizer.throttling.allow', 100);
        $this->every = config('resizer.throttling.every', 1);

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
     * @throws \Illuminate\Contracts\Redis\LimiterTimeoutException
     */
    public function handle(): void
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

    /**
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     * @throws \JsonException
     */
    private function process(): void
    {
        collect(\Storage::disk('shared')->files($this->uuid, false))
            ->mapWithKeys(function (string $path) {
                $image = \Image::make(\Storage::disk('shared')->get($path))->resize(100, 100);

                if ($this->webhook) {
                    return [$path => $image];
                }

                Redis::set('image-exp-' . $this->uuid, $expireTime = Carbon::parse(
                    config('resizer.abandon-job-at')
                ));
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
