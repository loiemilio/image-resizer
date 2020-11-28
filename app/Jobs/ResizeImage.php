<?php

namespace App\Jobs;

use App\Http\AsyncClient;
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

    /** @var string */
    private $uuid;

    /** @var string */
    private $webhook;
    /** @var AsyncClient|null */
    private $client;

    /**
     * ResizeImage constructor.
     * @param string $uuid Unique identifier of the job
     * @param UploadImageRequest $request The validated request made by the user, it contains an array of 'images'
     * in the form of object {name, data} with data being a base64 version of the image
     * @param AsyncClient|null $client Useful to extend the default client. If nothing provided an instance of a bare AsyncClient will be used
     */
    public function __construct(string $uuid, UploadImageRequest $request, AsyncClient $client = null)
    {
        $this->client = $client ?? new AsyncClient;

        $this->uuid = $uuid;
        $this->webhook = $request->input('webhook');

        collect($request->input('images'))
            ->map(function (array $image) {
                \Storage::disk('shared')->put(vsprintf('%s/%s', [
                    $this->uuid,
                    data_get($image, 'name'),
                ]), base64_decode(data_get($image, 'data')));

                return data_get($image, 'name');
            })->all();
    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws \Illuminate\Contracts\Redis\LimiterTimeoutException
     */
    public function handle(): void
    {
        //TODO is throttling necessary at this level?
        Redis::throttle(class_basename($this))
            ->allow(config('resizer.throttling.allow'))
            ->every(config('resizer.throttling.every'))
            ->then(function () {
                if (Redis::set('image-' . $this->uuid, getmypid(), 'EX', 10 * 60, 'NX')) {
                    $this->process();
                    Redis::del('image-' . $this->uuid);
                } else {
                    $this->release(config('resizer.throttling.allow'));
                }
            }, function () {
                $this->release(config('resizer.throttling.allow'));
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
                // For each image in the {uuid} folder create a resized version
                $image = \Image::make(\Storage::disk('shared')->get($path))->resize(100);

                if ($this->webhook) {
                    // When a webhook is provided then we don't need to signal the job has been completed
                    // or set an expiry time for the images, so we return
                    return [$path => $image];
                }

                // Otherwise let's create the relevant Redis keys
                Redis::set('image-done-' . $this->uuid, 1);
                Redis::set('image-exp-' . $this->uuid, $expireTime = Carbon::parse(
                    config('resizer.abandon-job-at')
                ));

                // and replace the original image with the resized one
                \Storage::disk('shared')->put($path, $image->stream());

                return [$path => $expireTime];
            })->when($this->webhook, function (Collection $files) {
                // We must callback the webhook
                // Let's construct the payload of our request with the uuid and the list of images
                // The images are object {name, data} with the data being the base64 encoded version of them
                $payload = [
                    'uuid' => $this->uuid,
                    'images' => $files->map(function ($image, $key) {
                        return [
                            'name' => \Str::after($key, $this->uuid . '/'),
                            'data' => urlencode($image->stream()),
                        ];
                    })->values()->all(),
                ];

                // We tell our client to make the webhook call
                $this->client->postJson($this->webhook, $payload);

                // And can now safely delete the {uuid} directory
                \Storage::disk('shared')->deleteDirectory($this->uuid);
            });
    }
}
