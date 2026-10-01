<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecordContributionRequest extends FormRequest
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
            'payment_provider' => ['required', 'in:wave,orange_money,mtn_momo'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'transaction_reference' => ['required', 'string', 'max:255', 'unique:contributions,transaction_reference'],
            'payment_timestamp' => ['nullable', 'date'],
        ];
    }

    /**
     * Custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'payment_provider.in' => 'Le fournisseur de paiement doit être "wave", "orange_money" ou "mtn_momo".',
            'transaction_reference.unique' => 'Cette référence de transaction a déjà été utilisée.',
        ];
    }
}
