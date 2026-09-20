<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Ajuste a lógica de autorização/admin se necessário
    }

    protected function prepareForValidation(): void
    {
        // Gera o slug automaticamente caso não venha na requisição
        if ($this->has('name')) {
            $this->merge([
                'slug' => Str::slug($this->name),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:50|unique:tags,name',
            'slug' => 'required|string|max:60|unique:tags,slug',
            'category' => 'required|in:area,technology,seniority,soft_skill',
            'description' => 'nullable|string|max:255',
            'display_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ];
    }
}
