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
            'actual_date' => 'required|date',
            'inv_nos' => 'required|array|min:1',
            'inv_nos.*' => 'string',
        ];
    }

    public function messages(): array
    {
        return [
            'actual_date.required' => 'The actual_date field is required.',
            'actual_date.date'     => 'The actual_date must be a valid date.',
            'inv_nos.required' => 'The inv_nos field is required.',
            'inv_nos.array' => 'The inv_nos field must be an array.',
            'inv_nos.min' => 'At least one invoice number must be provided.',
            'inv_nos.*.string' => 'Each invoice number must be a string.',
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
