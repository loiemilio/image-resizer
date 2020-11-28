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
     */
    public function doesntProcessWhenThrottled(): void
    {
        config([
            'resizer.throttling.allow' => 1,
            'resizer.throttling.every' => 10,
        ]);

        \Storage::fake('shared');

        ResizeImage::dispatchNow($uuid = \Str::uuid(), new UploadImageRequest([
            'images' => [
                $this->image(),
            ],
        ]));

        self::assertTrue(\Storage::disk('shared')->exists($uuid));
        self::assertTrue((bool)\Redis::exists('image-done-' . $uuid));

        ResizeImage::dispatchNow($uuid = \Str::uuid(), new UploadImageRequest([
            'images' => [
                $this->image(),
            ],
        ]));

        self::assertTrue(\Storage::disk('shared')->exists($uuid));
        self::assertFalse((bool)\Redis::exists('image-done-' . $uuid));
    }

    /**
     * @test
     * @return void
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function resizesTheImages(): void
    {
        \Storage::fake('shared');

        ResizeImage::dispatchNow($uuid = \Str::uuid(), new UploadImageRequest([
            'images' => [
                $this->image(),
            ],
        ]));

        self::assertTrue(\Storage::disk('shared')->exists($uuid));

        $originalImage = file_get_contents(storage_path('tests/a.jpg'));
        $newImage = \Storage::disk('shared')->get(vsprintf('%s/%s', [
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
        \Storage::fake('shared');

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
