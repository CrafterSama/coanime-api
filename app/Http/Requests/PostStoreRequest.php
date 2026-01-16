<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PostStoreRequest extends FormRequest
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
            'excerpt' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'image' => ['required', 'string', 'max:255'],
            'postponed_to' => ['nullable', 'date_format:Y-m-d H:i:s'],
            'tag_id' => ['nullable', 'array'],
            'tag_id.*' => ['nullable', 'integer', 'exists:tags,id'],
            'title_id' => ['nullable', 'integer', 'exists:titles,id'],
            'draft' => ['nullable', 'boolean'],
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
            'excerpt.required' => 'El extracto es obligatorio.',
            'excerpt.max' => 'El extracto no puede exceder 255 caracteres.',
            'content.required' => 'El contenido es obligatorio.',
            'category_id.required' => 'La categoría es obligatoria.',
            'category_id.exists' => 'La categoría seleccionada no es válida.',
            'image.required' => 'La imagen es obligatoria.',
            'postponed_to.date_format' => 'La fecha debe tener el formato Y-m-d H:i:s.',
        ];
    }
}
