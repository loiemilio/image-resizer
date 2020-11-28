<?php

namespace Tests\Feature\Commands;

use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class FlushImagesTest extends TestCase
{
    /**
     * @test
     * @return void
     */
    public function itDeletesJobsAndImagesWhenExpired(): void
    {
        $uuid = \Str::uuid();
        \Storage::fake(config('resizer.disk'));
        \Storage::disk(config('resizer.disk'))->put($uuid, UploadedFile::fake()->image('test.jpg'));

        self::assertTrue(\Storage::disk(config('resizer.disk'))->exists($uuid));

        \Redis::set('image-exp-' . $uuid, now()->subMinute());
        \Redis::set('image-done-' . $uuid, 1);

        self::assertNotNull(\Redis::get('image-exp-' . $uuid));
        self::assertNotNull(\Redis::get('image-done-' . $uuid));

        $this->artisan('images:flush')->assertExitCode(0);

        self::assertNull(\Redis::get('image-exp-' . $uuid));
        self::assertNull(\Redis::get('image-done-' . $uuid));

        self::assertFalse(\Storage::disk(config('resizer.disk'))->exists($uuid));
    }

    /**
     * @test
     * @return void
     */
    public function itDoesntDeleteJobsAndImagesWhenNotExpired(): void
    {
        $uuid = \Str::uuid();
        \Storage::fake(config('resizer.disk'));
        \Storage::disk(config('resizer.disk'))->put($uuid, UploadedFile::fake()->image('test.jpg'));

        self::assertTrue(\Storage::disk(config('resizer.disk'))->exists($uuid));

        \Redis::set('image-exp-' . $uuid, now()->addMinute());
        \Redis::set('image-done-' . $uuid, 1);

        self::assertNotNull(\Redis::get('image-exp-' . $uuid));
        self::assertNotNull(\Redis::get('image-done-' . $uuid));

        $this->artisan('images:flush')->assertExitCode(0);

        self::assertNotNull(\Redis::get('image-exp-' . $uuid));
        self::assertNotNull(\Redis::get('image-done-' . $uuid));

        self::assertTrue(\Storage::disk(config('resizer.disk'))->exists($uuid));
    }

}
