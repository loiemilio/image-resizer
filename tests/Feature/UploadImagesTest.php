<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
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
    public function notFoundOnInvalidUUID(): void
    {
        $response = $this->post('/invalid-uuid');
        $response->assertStatus(404);
    }

    /**
     * @test
     * @return void
     */
    public function validationErrorWhenNoImageProvided(): void
    {
        $response = $this->post('/');
        $response->assertStatus(422);
        $response->assertJsonFragment([
            'image' => [
                'Provide one between image and images parameters',
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
            'image' => '1234',
        ]);
        $response->assertStatus(422);
        $response->assertJsonFragment([
            'image' => [
                'The file must be an image.',
            ],
        ]);

        $response = $this->post('/', [
            'images' => '1234',
        ]);
        $response->assertStatus(422);
        $response->assertJsonFragment([
            'images' => [
                'The list of files must be an array.',
            ],
        ]);

        $response = $this->post('/', [
            'images' => [
                'invalid-file',
                UploadedFile::fake()->create('a.txt'),
                UploadedFile::fake()->image('a.png'),
            ],
        ]);
        $response->assertStatus(422);
        $response->assertJsonFragment([
            'images.0' => [
                'The file must be an image.',
            ],
            'images.1' => [
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
            'image' => UploadedFile::fake()->createWithContent(
                'a.png',
                file_get_contents(storage_path('tests/c.png'))
            ),
        ])
            ->assertStatus(422)
            ->assertJsonFragment([
                'image' => [
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
            'image' => UploadedFile::fake()->createWithContent(
                'a.png',
                file_get_contents(storage_path('tests/c.png'))
            ),
        ])
            ->assertStatus(200)
            ->assertJsonStructure([
                'uuid',
            ]);
    }

}
