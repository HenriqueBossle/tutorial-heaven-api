<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncUserTagsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tag_ids' => 'required|array|min:1',
            'tag_ids.*' => 'integer|exists:tags,id',
        ];
    }
}
