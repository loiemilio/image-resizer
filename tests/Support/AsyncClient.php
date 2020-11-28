<?php

namespace Tests\Support;

use Illuminate\Support\Facades\Http;

class AsyncClient extends \App\Http\AsyncClient
{
    public function postJson($url, $params, $headers = [])
    {
        Http::fake();
        Http::withHeaders($headers)->post($url, $params);
    }

}
