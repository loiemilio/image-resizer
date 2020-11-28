<?php

namespace App\Http\Controllers;

use App\Http\Requests\UploadImageRequest;
use App\Jobs\DeleteImages;
use App\Jobs\ResizeImage;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Collection;

/**
 * Class Controller
 * @package App\Http\Controllers
 * @OA\Info(
 *     title="GeckoSoft Image Resizer",
 *     version="0.0.1b",
 * )
 */
class Controller extends BaseController
{
    use DispatchesJobs, ValidatesRequests;

    /**
     * @param string $uuid
     * @return \Illuminate\Http\JsonResponse
     *
     * @OA\Get(
     *      path="/{uuid}",
     *      summary="Get status or result of a job",
     *      operationId="show",
     *      @OA\Response(
     *          response=404,
     *          description="Job not found",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Not Found"),
     *          ),
     *      ),
     *     @OA\Response(
     *          response=202,
     *          description="Job not yet processed",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Images not yet processed"),
     *          ),
     *      ),
     * )
     */
    public function show(string $uuid)
    {
        abort_unless(\Redis::get(vsprintf('image-exp-%s', [$uuid])), 404, 'Job uuid not found.');
        abort_unless(\Redis::get(vsprintf('image-done-%s', [$uuid])), 202, 'Images not yet processed.');

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

    /**
     * @param UploadImageRequest $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @OA\Post(
     *      path="/",
     *      summary="Upload the images to resize",
     *      operationId="upload",
     *      @OA\RequestBody(
     *          required=true,
     *          description="Upload the images",
     *          @OA\JsonContent(
     *              required={"images"},
     *              @OA\Property(property="images", type="string")
     *          )
     *      ),
     *      @OA\Response(
     *          response=422,
     *          description="Wrong payload",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="The given data is invalid"),
     *              @OA\Property(property="errors", type="object", example="{images:[]}")
     *          )
     *      ),
     * )
     */
    public function upload(UploadImageRequest $request)
    {
        ResizeImage::dispatch($uuid = \Str::uuid(), $request);

        return response()->json([
            'uuid' => $uuid,
        ]);
    }
}
