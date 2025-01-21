<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Exceptions\HttpResponseException;

class UserStoreRequest extends FormRequest
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
            'supplier_code' => 'required|string|max:25',
            'name' => 'required|string|max:25',
            'role' => 'required|string|max:25',
            'status' => 'required|string|max:25',
            'username' => 'required|string|unique:user,username|max:25', // username must unique
            'password' => 'required|string|min:8|max:25', //min and max length 8/25
            'email.*' => 'email|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'supplier_code.required' => 'The supplier_code field is required.',
            'supplier_code.string' => 'The supplier_code must be a string.',
            'supplier_code.max' => 'The supplier_code may not be greater than 25 characters.',
            'name.required' => 'The name field is required.',
            'name.string' => 'The name must be a string.',
            'name.max' => 'The name may not be greater than 25 characters.',
            'role.required' => 'The role field is required.',
            'role.string' => 'The role must be a string.',
            'role.max' => 'The role may not be greater than 25 characters.',
            'status.required' => 'The status field is required.',
            'status.string' => 'The status must be a string.',
            'status.max' => 'The status may not be greater than 25 characters.',
            'username.required' => 'The username field is required.',
            'username.string' => 'The username must be a string.',
            'username.unique' => 'The username has already been taken.',
            'username.max' => 'The username may not be greater than 25 characters.',
            'password.required' => 'The password field is required.',
            'password.string' => 'The password must be a string.',
            'password.min' => 'The password must be at least 8 characters.',
            'password.max' => 'The password may not be greater than 25 characters.',
            'email.*.email' => 'Each email must be a valid email address.',
            'email.*.max' => 'Each email may not be greater than 255 characters.',
        ];
    }

    // Failed validation response
    protected function failedValidation($validator)
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422)
        );
    }
}
