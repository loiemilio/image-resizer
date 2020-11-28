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
                'max:3000',
            ],
            'images.*' => [
                'required_without:image',
                'image',
                'max:3000',
            ],
            'webhook' => [
                'sometimes',
                'url',
            ],
        ];
    }
}
