<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;

class FinanceNewsStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Example: allow only users with role 2, adjust as needed
        return Auth::user()->role == 2;
    }

    public function rules(): array
    {
        $rules = [
            'title' => 'nullable|string|max:255',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'document' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:5000',
            'carousel_images' => 'nullable|array|min:1',
            'carousel_images.*' => 'image|mimes:jpeg,jpg,png,webp|max:2048',
        ];

        // Only validate end_date after start_date if start_date is provided
        if ($this->has('start_date') && $this->start_date) {
            $rules['end_date'] = 'nullable|date|after_or_equal:start_date';
        }

        return $rules;
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $data = $this->all();

            // At least one of: title, document, or carousel_images must be provided
            $hasTitle = !empty($data['title']);
            $hasDocument = $this->hasFile('document');
            $hasCarouselImages = $this->hasFile('carousel_images') && count($this->file('carousel_images')) > 0;

            if (!$hasTitle && !$hasDocument && !$hasCarouselImages) {
                $validator->errors()->add('carousel_images', 'At least one of title, document, or carousel_images must be provided.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'title.required' => 'The title field is required.',
            'title.string' => 'The title must be a string.',
            'title.max' => 'The title may not be greater than 255 characters.',
            'start_date.required' => 'The start date field is required.',
            'carousel_images.array' => 'The carousel images must be an array.',
            'carousel_images.*.image' => 'Each carousel image must be an image file.',
            'carousel_images.*.mimes' => 'Each carousel image must be a file of type: jpeg, jpg, png, webp.',
            'carousel_images.*.max' => 'Each carousel image may not be larger than 2048 kilobytes.',
            'start_date.date' => 'The start date must be a valid date.',
            'end_date.required' => 'The end date field is required.',
            'end_date.date' => 'The end date must be a valid date.',
            'end_date.after_or_equal' => 'The end date must be after or equal to the start date.',
            'document.file' => 'The document must be a file.',
            'document.mimes' => 'The document must be a file of type: pdf, doc, docx, jpg, jpeg, png.',
            'document.max' => 'The document may not be larger than 5000 kilobytes.',
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
