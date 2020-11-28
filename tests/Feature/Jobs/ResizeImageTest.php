<?php

namespace Tests\Feature\Jobs;

use App\Http\Requests\UploadImageRequest;
use App\Jobs\ResizeImage;
use Illuminate\Http\Client\Request;
use Intervention\Image\Facades\Image;
use Tests\Support\AsyncClient;
use Tests\TestCase;

class ResizeImageTest extends TestCase
{
    /**
     * @test
     * @return void
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function resizesTheImages(): void
    {
        \Storage::fake(config('resizer.disk'));

        ResizeImage::dispatchNow($uuid = \Str::uuid(), new UploadImageRequest([
            'images' => [
                $this->image(),
            ],
        ]));

        self::assertTrue(\Storage::disk(config('resizer.disk'))->exists($uuid));

        $originalImage = file_get_contents(storage_path('tests/a.jpg'));
        $newImage = \Storage::disk(config('resizer.disk'))->get(vsprintf('%s/%s', [
            $uuid,
            'a.jpg',
        ]));

        self::assertNotEquals($originalImage, $newImage);

        self::assertEquals(100, Image::make($newImage)->width());
        self::assertEquals(100, Image::make($newImage)->height());
    }

    /**
     * @test
     * @return void
     */
    public function callsBackTheWebhookWhenDone(): void
    {
        \Storage::fake(config('resizer.disk'));

        ResizeImage::dispatchNow($uuid = \Str::uuid(), new UploadImageRequest([
            'webhook' => $webhook = 'https://127.0.0.1',
            'images' => [
                $this->image(),
            ],
        ]), new AsyncClient);

        \Http::assertSent(function (Request $request) use ($webhook) {
            return $request->url() === $webhook
                && $request->method() === 'POST';
        });


    }

}
