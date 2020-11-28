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
            'image' => [
                'required_without:images',
                'image',
                'max:' . config('resizer.max-image-size'),
            ],
            'images' => [
                'required_without:image',
                'array',
            ],
            'images.*' => [
                'required_without:image',
                'image',
                'max:' . config('resizer.max-image-size'),
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
            'image.required_without' => 'Provide one between image and images parameters',
            'images.*.required_without' => 'Provide one between image and images parameters',
        ];
    }

    public function attributes()
    {
        return [
            'image' => 'file',
            'images.*' => 'file',
            'images' => 'list of files',
        ];
    }
}
