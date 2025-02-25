<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;

class SuperAdminInvHeaderStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::user()->role == 1;
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
            'inv_faktur_date' => 'required|date',
            'inv_supplier' => 'required|string|max:50',
            'status'       => 'nullable|string',
            'reason'       => 'nullable|string',
            'created_by'   => 'nullable|string',
            'ppn_id'       => 'required|exists:inv_ppn,ppn_id',
            'pph_id'       => 'required|exists:inv_pph,pph_id',
            'invoice_file' => 'nullable|file|mimes:pdf|max:5000',
            'fakturpajak_file' => 'nullable|file|mimes:pdf|max:5000',
            'suratjalan_file' => 'nullable|file|mimes:pdf|max:5000',
            'po_file' => 'nullable|file|mimes:pdf|max:5000',
            'inv_line_detail' => 'required|array',
        ];
    }

    public function messages(): array
    {
        return [
            'inv_no.required'              => 'The inv_no field is required.',
            'inv_no.string'                => 'The inv_no must be a string.',
            'inv_no.max'                   => 'The inv_no may not be greater than 255 characters.',
            'inv_date.required'            => 'The inv_date field is required.',
            'inv_date.date'                => 'The inv_date must be a valid date.',
            'inv_faktur.required'          => 'The inv_faktur field is required.',
            'inv_faktur.string'            => 'The inv_faktur must be a string.',
            'inv_faktur.max'               => 'The inv_faktur may not be greater than 50 characters.',
            'inv_supplier.required'        => 'The inv_supplier field is required.',
            'inv_supplier.string'          => 'The inv_supplier must be a string.',
            'inv_supplier.max'             => 'The inv_supplier may not be greater than 50 characters.',
            'ppn_id.required'              => 'The ppn_id field is required.',
            'ppn_id.exists'                => 'The selected ppn_id doesn’t exist.',
            'invoice_file.file'            => 'The invoice_file must be a file.',
            'invoice_file.mimes'           => 'The invoice_file must be a PDF.',
            'invoice_file.max'             => 'The invoice_file may not be larger than 5000 kilobytes.',
            'fakturpajak_file.file'        => 'The fakturpajak_file must be a file.',
            'fakturpajak_file.mimes'       => 'The fakturpajak_file must be a PDF.',
            'fakturpajak_file.max'         => 'The fakturpajak_file may not be larger than 5000 kilobytes.',
            'suratjalan_file.file'         => 'The suratjalan_file must be a file.',
            'suratjalan_file.mimes'        => 'The suratjalan_file must be a PDF.',
            'suratjalan_file.max'          => 'The suratjalan_file may not be larger than 5000 kilobytes.',
            'po_file.file'                 => 'The po_file must be a file.',
            'po_file.mimes'                => 'The po_file must be a PDF.',
            'po_file.max'                  => 'The po_file may not be larger than 5000 kilobytes.',
            'inv_line_detail.required'     => 'The inv_line_detail field is required.',
            'inv_line_detail.array'        => 'The inv_line_detail must be an array.',
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
