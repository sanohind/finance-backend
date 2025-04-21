<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;

class SuperAdminPaymentDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->role == 1;
    }

    public function rules(): array
    {
        return [
            'payment_file' => 'required|file|mimes:pdf|max:2048',
        ];
    }

    public function messages(): array
    {
        return [
            'payment_file.required' => 'The payment file is missing from the request.',
            'payment_file.file'     => 'The uploaded payment_file must be a valid file.',
            'payment_file.mimes'    => 'The payment file must be a PDF document.',
            'payment_file.max'      => 'The payment file must not exceed 2MB in size.',
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
