<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;

class FinancePaymentDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->role == 2;
    }

    public function rules(): array
    {
        return [
            'actual_date' => 'required|date',
        ];
    }

    public function messages(): array
    {
        return [
            'actual_date.required' => 'The actual_date field is required.',
            'actual_date.date'     => 'The actual_date must be a valid date.',
        ];
    }

    protected function failedValidation($validator)
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422)
        );
    }
}
