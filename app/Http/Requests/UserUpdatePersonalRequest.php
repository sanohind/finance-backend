<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Exceptions\HttpResponseException;

class UserUpdatePersonalRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return in_array(Auth::user()->role, [2, 3]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = Auth::user() ? Auth::user()->user_id : null;

        return [
            'name'     => 'required|string|max:25',
            'password' => 'nullable|string|min:8',
            'username' => 'nullable|string|unique:users,username,'.$userId.',user_id|max:25',
            'email'    => 'required|email|max:255',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required'     => 'The name is required.',
            'name.string'       => 'The name must be a string.',
            'name.max'          => 'The name may not be greater than 25 characters.',
            'password.string'   => 'The password must be a string.',
            'password.min'      => 'The password must be at least 8 characters.',
            'username.string'   => 'The username must be a string.',
            'username.unique'   => 'The username has already been taken.',
            'username.max'      => 'The username may not be greater than 25 characters.',
            'email.required'    => 'The email is required.',
            'email.email'       => 'The email must be a valid email address.',
            'email.max'         => 'The email may not be greater than 255 characters.',
        ];
    }

    // Failed validation response
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
