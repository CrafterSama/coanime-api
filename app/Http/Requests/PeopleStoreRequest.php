<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PeopleStoreRequest extends FormRequest
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
            'japanese_name' => ['required', 'string'],
            'areas_skills_hobbies' => ['required', 'string'],
            'bio' => ['required', 'string'],
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'birthday' => ['nullable', 'date_format:Y-m-d H:i:s'],
            'country_code' => ['required', 'string', 'size:2'],
            'falldown' => ['required', 'string'],
            'falldown_date' => ['nullable', 'date_format:Y-m-d H:i:s'],
            'image-client' => ['nullable', 'image', 'mimes:jpeg,gif,bmp,png', 'max:2048'],
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
            'name.max' => 'El nombre no puede exceder 255 caracteres.',
            'japanese_name.required' => 'El nombre en japonés es obligatorio.',
            'areas_skills_hobbies.required' => 'Las áreas, habilidades y hobbies son obligatorias.',
            'bio.required' => 'La biografía es obligatoria.',
            'city_id.required' => 'La ciudad es obligatoria.',
            'city_id.exists' => 'La ciudad seleccionada no es válida.',
            'birthday.date_format' => 'La fecha de nacimiento debe tener el formato Y-m-d H:i:s.',
            'country_code.required' => 'El código de país es obligatorio.',
            'country_code.size' => 'El código de país debe tener 2 caracteres.',
            'falldown.required' => 'El campo falldown es obligatorio.',
            'falldown_date.date_format' => 'La fecha de falldown debe tener el formato Y-m-d H:i:s.',
            'image-client.image' => 'El archivo debe ser una imagen.',
            'image-client.mimes' => 'La imagen debe ser de tipo: jpeg, gif, bmp, png.',
            'image-client.max' => 'La imagen no puede exceder 2MB.',
        ];
    }
}
