<?php

namespace App\Http\Controllers;

use App\Http\Requests\UploadImageRequest;
use App\Jobs\ResizeImage;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use DispatchesJobs, ValidatesRequests;

    public function upload(UploadImageRequest $request)
    {
        $uuid = \Str::uuid();
        ResizeImage::dispatchNow($uuid, $request);


        return response()->json([
            'uuid' => $uuid,
        ]);
    }

}
