<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;

class SupplierInvHeaderStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::user()->role == 3;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'inv_no'       => 'required|string|max:255',
            'inv_date'     => 'required|date',
            'inv_faktur'   => 'required|string|max:50',
            'inv_supplier' => 'required|string|max:50',
            'status'       => 'nullable|string',
            'reason'       => 'nullable|string',
            'invoice_file' => 'nullable|file|mimes:pdf|max:2048',
            'fakturpajak_file' => 'nullable|file|mimes:pdf|max:2048',
            'suratjalan_file' => 'nullable|file|mimes:pdf|max:2048',
            'po_file' => 'nullable|file|mimes:pdf|max:2048',
            'inv_line_detail' => 'required|array',
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
