<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PostUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // La autorización se maneja en el middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'file' => ['nullable', 'image', 'mimes:jpg,jpeg,gif,bmp,png', 'max:2048'],
            'postponed_to' => ['nullable', 'date_format:Y-m-d H:i:s'],
            'tag_id' => ['nullable', 'array'],
            'tag_id.*' => [
                'nullable',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (is_numeric($value)) {
                        if (! \App\Models\Tag::where('id', (int) $value)->exists()) {
                            $fail('El tag seleccionado no es válido.');
                        }
                    } elseif (is_string($value)) {
                        if (strlen($value) > 255) {
                            $fail('El nombre del tag no puede exceder 255 caracteres.');
                        }
                    } else {
                        $fail('Cada tag debe ser un ID numérico o un nombre.');
                    }
                },
            ],
            'title_id' => ['nullable', 'integer', 'exists:titles,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'El título es obligatorio.',
            'title.max' => 'El título no puede exceder 255 caracteres.',
            'content.required' => 'El contenido es obligatorio.',
            'category_id.required' => 'La categoría es obligatoria.',
            'category_id.exists' => 'La categoría seleccionada no es válida.',
            'file.image' => 'El archivo debe ser una imagen.',
            'file.mimes' => 'La imagen debe ser de tipo: jpg, jpeg, gif, bmp, png.',
            'file.max' => 'La imagen no puede exceder 2MB.',
        ];
    }
}
