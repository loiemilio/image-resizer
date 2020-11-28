<?php

namespace Tests\Feature\Jobs;

use App\Http\Requests\UploadImageRequest;
use App\Jobs\ResizeImage;
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

}
