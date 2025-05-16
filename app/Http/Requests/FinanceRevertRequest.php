<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;

class FinanceRevertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->role == 2;
    }

    public function rules(): array
    {
        return [
            // No specific rules needed if inv_nos is not expected from the body for this request.
        ];
    }

    public function messages(): array
    {
        return [
            // No specific messages needed if there are no rules.
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
