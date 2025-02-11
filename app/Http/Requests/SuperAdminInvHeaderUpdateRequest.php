<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;

class SuperAdminInvHeaderUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only allow access if user role == 1 (Admin/SuperAdmin)
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
            'pph_id'           => 'required|exists:inv_pph,pph_id',
            'pph_base_amount'  => 'required|numeric',
            'inv_line_remove'       => 'nullable|array',
            'inv_line_remove.*'     => 'exists:inv_line,inv_line_id',
            'status'           => 'required|string|max:50',
            'reason'           => 'nullable|string|max:255',
            'updated_by'       => 'nullable|string|max:100',
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
