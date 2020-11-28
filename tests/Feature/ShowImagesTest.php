<?php

namespace Tests\Feature;

use Tests\TestCase;

class ShowImagesTest extends TestCase
{

    /**
     * @test
     * @return void
     */
    public function notFoundOnInvalidUUID(): void
    {
        $response = $this->get('/invalid-uuid');
        $response->assertStatus(404);
    }

    /**
     * @test
     * @return void
     */
    public function notFoundOnUnknownUUID(): void
    {
        $response = $this->get('/' . \Str::uuid());
        $response->assertStatus(404)
            ->assertJsonFragment([
                'message' => 'UUID not found.',
            ]);
    }

    /**
     * @test
     * @return void
     */
    public function acceptedOnNotYetProcessedJob(): void
    {
        $uuid = \Str::uuid();
        \Redis::set('image-exp-' . $uuid, now()->addMinute());
        $response = $this->get('/' . $uuid);
        $response->assertStatus(202)
            ->assertJsonFragment([
                'message' => 'Images not yet processed.',
            ]);
    }

    /**
     * @test
     * @return void
     */
    public function itReturnsTheImages(): void
    {
        $uuid = \Str::uuid();
        \Storage::fake(config('resizer.disk'));
        \Storage::disk(config('resizer.disk'))->put(vsprintf('%s/%s', [
            $uuid,
            'a.jpg',
        ]), file_get_contents(storage_path('tests/a.jpg')));

        \Redis::set('image-exp-' . $uuid, now()->addMinute());
        \Redis::set('image-done-' . $uuid, 1);

        $response = $this->get('/' . $uuid);
        $response->assertStatus(200)
            ->assertJsonFragment([
                'name' => 'a.jpg',
                'content' => file_get_contents(storage_path('tests/a.jpg')),
            ]);
    }

    /**
     * @test
     * @return void
     */
    public function itCleanupsAfterShowing(): void
    {
        $uuid = \Str::uuid();
        \Storage::fake(config('resizer.disk'));
        \Storage::disk(config('resizer.disk'))->put(vsprintf('%s/%s', [
            $uuid,
            'a.jpg',
        ]), file_get_contents(storage_path('tests/a.jpg')));

        \Redis::set('image-exp-' . $uuid, now()->addMinute());
        \Redis::set('image-done-' . $uuid, 1);

        $this->get('/' . $uuid)->assertStatus(200);

        self::assertNull(\Redis::get('image-exp-' . $uuid));
        self::assertNull(\Redis::get('image-done-' . $uuid));
        self::assertFalse(\Storage::disk(config('resizer.disk'))->exists($uuid));

        $this->get('/' . $uuid)->assertStatus(404);
    }
}
