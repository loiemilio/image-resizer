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

        // For every image in the {uuid} folder set the expiry time
        Redis::set('image-exp-' . $this->uuid, $expireTime = Carbon::parse(
            config('resizer.abandon-job-at')
        ));

        collect($request->input('images'))
            ->map(function (array $image) {
                \Storage::disk(config('resizer.disk'))->put(vsprintf('%s/%s', [
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
        collect(\Storage::disk(config('resizer.disk'))->files($this->uuid, false))
            ->mapWithKeys(function (string $path) {
                // Create the resized version
                $image = \Image::make(\Storage::disk(config('resizer.disk'))->get($path))->resize(100, 100);

                if ($this->webhook) {
                    // When a webhook is provided then we don't need to signal that the job has been completed
                    // and images are sent immediately to the user, the we return
                    return [$path => $image];
                }

                // Otherwise let's create the relevant Redis keys
                Redis::set('image-done-' . $this->uuid, true);

                // and replace the original image with the resized one
                \Storage::disk(config('resizer.disk'))->put($path, $image->stream());

                return [$path => true];
            })->when($this->webhook, function (Collection $files) {
                // We must call the webhook back
                // The payload of our request has the job uuid and the list of images
                // The images are simple objects {name, data} with the data being base64 encoded
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
                \Storage::disk(config('resizer.disk'))->deleteDirectory($this->uuid);
            });
    }
}
