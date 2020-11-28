<?php

namespace App\Jobs;

use App\Http\Requests\UploadImageRequest;
use GuzzleHttp\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Intervention\Image\Image;

class ResizeImage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
            $request->file('image')
        );
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {

        array_map(function (string $path) {
            $image = \Image::make(\Storage::disk('shared')->get($path))->resize(100, 100);

            if ($this->webhook) {
                $this->sendImage($image);
            } else {
                // TODO store file somewhere temporary
                \Storage::disk('shared')->put($path, $image->stream());
            }
        }, \Storage::disk('shared')->files($this->uuid, false));
    }

    private function client()
    {
        if (!$this->client) {
            $this->client = new Client;
        }
    }

    private function sendImage(Image $image)
    {
        $this->client->postAsync($this->webhook, [
            'body' => [
                'uuid' => $this->uuid,
                'image' => $image->stream(),
            ],
        ]);
    }
}
