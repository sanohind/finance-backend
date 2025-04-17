<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Exceptions\HttpResponseException;

class FinanceInvHeaderUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::user()->role == 2;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'pph_id'           => 'required_if:status,Ready To Payment|exists:inv_pph,pph_id',
            'pph_base_amount'  => 'required_if:status,Ready To Payment|numeric',
            'inv_line_remove'  => 'nullable|array',
            'inv_line_remove.*'=> 'exists:inv_line,inv_line_id',
            'status'           => 'required|string|max:50|in:Ready To Payment,Rejected',
            'plan_date'        => 'required_if:status,Ready To Payment|date',
            'reason'           => 'required_if:status,Rejected',
            'updated_by'       => 'nullable|string|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'pph_id.required'             => 'The pph_id field is required.',
            'pph_id.exists'               => 'The selected pph_id doesn’t exist.',
            'pph_base_amount.required'    => 'The pph_base_amount field is required.',
            'pph_base_amount.numeric'     => 'The pph_base_amount must be numeric.',
            'inv_line_remove.array'       => 'The inv_line_remove must be an array.',
            'inv_line_remove.*.exists'    => 'One or more inv_line IDs do not exist in the database.',
            'status.required'             => 'The status field is required.',
            'status.string'               => 'The status must be a string.',
            'status.max'                  => 'The status may not be greater than 50 characters.',
            'status.in'                   => 'The status must be either Ready To Payment or Rejected.',
            'reason.string'               => 'The reason must be a string.',
            'reason.max'                  => 'The reason may not be greater than 255 characters.',
            'updated_by.string'           => 'The updated_by field must be a string.',
            'updated_by.max'              => 'The updated_by may not be greater than 100 characters.',
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
