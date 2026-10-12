<?php

namespace App\Http\Requests\Concerns;

trait DecodesBase64Content
{
    private bool $contentIsValidBase64Utf8 = false;

    protected function prepareForValidation(): void
    {
        $content = $this->input('content');

        if (! is_string($content)) {
            return;
        }

        $decoded = base64_decode($content, true);

        if ($decoded === false || ! mb_check_encoding($decoded, 'UTF-8')) {
            return;
        }

        $this->contentIsValidBase64Utf8 = true;
        $this->merge(['content' => $decoded]);
    }

    protected function base64ContentRules(): array
    {
        return [
            'required',
            'string',
            function (string $attribute, mixed $value, \Closure $fail): void {
                if (is_string($value) && ! $this->contentIsValidBase64Utf8) {
                    $fail('O campo :attribute deve ser uma string Base64 válida contendo texto UTF-8.');
                }
            },
        ];
    }
}
