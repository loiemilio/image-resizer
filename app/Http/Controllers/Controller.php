<?php

namespace App\Http\Controllers;

use App\Http\Requests\UploadImageRequest;
use App\Jobs\DeleteImages;
use App\Jobs\ResizeImage;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use DispatchesJobs, ValidatesRequests;

    public function show($uuid)
    {
        abort_unless(\Redis::get(vsprintf('image-exp-%s', [$uuid])), 404);

        return response()->json([
            'images' => collect(\Storage::disk('shared')->files($uuid, false))
                ->map(function ($path) use ($uuid) {
                    return [
                        'name' => \Str::after($path, $uuid . '/'),
                        'content' => base64_encode(\Storage::disk('shared')->get($path)),
                    ];
                })->whenNotEmpty(function ($paths) use ($uuid) {
                    DeleteImages::dispatchNow($uuid);

                    return $paths;
                })->all(),
        ]);
    }

    public function upload(UploadImageRequest $request)
    {
        $uuid = \Str::uuid();
        ResizeImage::dispatchNow($uuid, $request);

        return response()->json([
            'uuid' => $uuid,
        ]);
    }

}
