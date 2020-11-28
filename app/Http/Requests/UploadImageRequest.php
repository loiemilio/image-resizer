<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadImageRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'images' => [
                'required',
                'array',
                'min:1',
            ],
            'images.*.name' => [
                'required',
                'distinct',
                'not_regex:/[\\/:]/',
                'max:256',
            ],
            'images.*.data' => [
                'required',
                function ($attribute, $value, $fail) {
                    $payload = base64_decode($value);
                    if (!$payload) {
                        $fail('The data must be a base64_encoded string.');
                    }

                    if (strlen($payload) > 1024 * ($kb = config('resizer.max-image-size'))) {
                        $fail('The file may not be greater than ' . $kb . ' kilobytes.');
                    }

                    $info = new \finfo(FILEINFO_MIME_TYPE);
                    if (!in_array($info->buffer($payload), config('resizer.allowed-mimetypes'), true)) {
                        $fail('The file must be an image.');
                    }
                },
            ],
            'webhook' => [
                'sometimes',
                'url',
            ],
        ];
    }

    public function messages()
    {
        return [
            'images.required' => 'Provide at least one image.',
            'images.min' => 'Provide at least one image.',

            'images.*.name.required' => 'Provide a name for every image.',
            'images.*.name.distinct' => 'Provide a different name for every image.',
            'images.*.data.required' => 'Provide a base64 encoded image.',
        ];
    }
}
