<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function image($image = 'a.jpg')
    {
        return [
            'name' => $image,
            'data' => base64_encode(file_get_contents(storage_path('tests/'.$image))),
        ];
    }
}
