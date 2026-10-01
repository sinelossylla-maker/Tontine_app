<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateTontineRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
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
            'contribution_amount' => ['required', 'numeric', 'gt:0'],
            'frequency' => ['required', 'in:weekly,monthly'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'max_members' => ['required', 'integer', 'min:2', 'max:100'],
        ];
    }

    /**
     * Custom validation messages.
     */
    public function messages(): array
    {
        return [
            'frequency.in' => 'La fréquence doit être "weekly" ou "monthly".',
            'start_date.after_or_equal' => 'La date de début doit être aujourd\'hui ou une date future.',
            'max_members.min' => 'Le nombre maximum de membres doit être au moins de 2.',
        ];
    }
}
