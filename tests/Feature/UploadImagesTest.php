<?php

namespace Tests\Feature;

use Illuminate\Support\Str;
use Tests\TestCase;

class UploadImagesTest extends TestCase
{
    /** @var string */
    private $uuid;

    protected function setUp(): void
    {
        $this->uuid = Str::uuid();

        parent::setUp();
    }

    /**
     * @test
     * @return void
     */
    public function itErrorsOnWrongMethodRequests(): void
    {
        $response = $this->get('/');
        $response->assertStatus(405);

        $response = $this->post('/' . $this->uuid);
        $response->assertStatus(405);
    }

    /**
     * @test
     * @return void
     */
    public function validationErrorWhenNoImagesProvided(): void
    {
        $response = $this->post('/');
        $response->assertStatus(422);
        $response->assertJsonFragment([
            'images' => [
                'Provide at least one image.',
            ],
        ]);
    }

    /**
     * @test
     * @return void
     */
    public function validationErrorWhenInvalidFileProvided(): void
    {
        $response = $this->post('/', [
            'images' => ['1234'],
        ]);
        $response->assertStatus(422);
        $response->assertJsonFragment([
            'images.0.data' => [
                'Provide a base64 encoded image.',
            ],
        ]);

        $response = $this->post('/', [
            'images' => [
                ['data' => '1235'],
            ],
        ]);
        $response->assertStatus(422);
        $response->assertJsonFragment([
            'images.0.name' => [
                'Provide a name for every image.',
            ],
        ]);

        $response = $this->post('/', [
            'images' => [
                ['name' => 'test.jpg', 'data' => '1235'],
            ],
        ]);
        $response->assertStatus(422);
        $response->assertJsonFragment([
            'images.0.data' => [
                'The file must be an image.',
            ],
        ]);
    }

    /**
     * @test
     * @return void
     */
    public function validationErrorWhenImageTooBig(): void
    {
        config(['resizer.max-image-size' => $kb = 200]);

        $this->post('/', [
            'images' => [
                ['name' => 'test.jpg', 'data' => base64_encode(file_get_contents(storage_path('tests/c.png')))],
            ],
        ])
            ->assertStatus(422)
            ->assertJsonFragment([
                'images.0.data' => [
                    'The file may not be greater than ' . $kb . ' kilobytes.',
                ],
            ]);
    }

    /**
     * @test
     * @return void
     */
    public function itReturnsAnUUIDWhenJobQueued(): void
    {
        $this->post('/', [
            'images' => [
                ['name' => 'test.jpg', 'data' => base64_encode(file_get_contents(storage_path('tests/c.png')))],
            ],
        ])
            ->assertStatus(200)
            ->assertJsonStructure([
                'uuid',
            ]);
    }

}
