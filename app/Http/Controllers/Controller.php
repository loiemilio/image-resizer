<?php

namespace App\Http\Controllers;

use App\Http\Requests\UploadImageRequest;
use App\Jobs\DeleteImages;
use App\Jobs\ResizeImage;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Collection;

class Controller extends BaseController
{
    use DispatchesJobs, ValidatesRequests;

    /**
     * @param string $uuid
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(string $uuid)
    {
        abort_unless(\Redis::get(vsprintf('image-exp-%s', [$uuid])), 404);
        abort_unless(\Redis::get(vsprintf('image-done-%s', [$uuid])), 202, 'Images not yet processed');

        return response()->json([
            'images' => collect(\Storage::disk('shared')->files($uuid, false))
                ->map(function (string $path) use ($uuid) {
                    return [
                        'name' => \Str::after($path, $uuid . '/'),
                        'content' => base64_encode(\Storage::disk('shared')->get($path)),
                    ];
                })->whenNotEmpty(function (Collection $paths) use ($uuid) {
                    DeleteImages::dispatchNow($uuid);

                    return $paths;
                })->all(),
        ]);
    }

    public function upload(UploadImageRequest $request)
    {
        ResizeImage::dispatch($uuid = \Str::uuid(), $request);

        return response()->json([
            'uuid' => $uuid,
        ]);
    }
}
