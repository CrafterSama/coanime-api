<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\TitleStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TitleUpdateRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'other_titles' => ['required', 'string'],
            'type_id' => ['required', 'integer', 'exists:titles_type,id'],
            'sinopsis' => ['required', 'string'],
            'episodies' => ['nullable', 'integer', 'min:0'],
            'just_year' => ['required', 'string'],
            'broad_time' => ['required', 'date_format:Y-m-d'],
            'broad_finish' => ['nullable', 'date_format:Y-m-d'],
            'genre_id' => ['required', 'array'],
            'genre_id.*' => ['integer', 'exists:genres,id'],
            'rating_id' => ['required', 'integer', 'exists:ratings,id'],
            'images' => ['nullable', 'string'],
            'status' => ['nullable', Rule::enum(TitleStatus::class)],
            'score' => ['nullable', 'numeric', 'min:0', 'max:10'],
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
            'name.required' => 'El nombre es obligatorio.',
            'other_titles.required' => 'Los otros títulos son obligatorios.',
            'type_id.required' => 'El tipo de título es obligatorio.',
            'type_id.exists' => 'El tipo de título seleccionado no es válido.',
            'sinopsis.required' => 'La sinopsis es obligatoria.',
            'episodies.integer' => 'El número de episodios debe ser un número entero.',
            'episodies.min' => 'El número de episodios no puede ser negativo.',
            'just_year.required' => 'El año es obligatorio.',
            'broad_time.required' => 'La fecha de emisión es obligatoria.',
            'broad_time.date_format' => 'La fecha de emisión debe tener el formato Y-m-d.',
            'broad_finish.date_format' => 'La fecha de finalización debe tener el formato Y-m-d.',
            'genre_id.required' => 'Al menos un género es obligatorio.',
            'rating_id.required' => 'La clasificación es obligatoria.',
            'rating_id.exists' => 'La clasificación seleccionada no es válida.',
        ];
    }
}
