<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class SupplierInvHeaderRejectedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->role == 3;
    }

    public function rules(): array
    {
        return [
            'reason' => 'required|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'The reason field is required.',
            'reason.string'   => 'The reason must be a string.',
            'reason.max'      => 'The reason may not be greater than 255 characters.',
        ];
    }
}
