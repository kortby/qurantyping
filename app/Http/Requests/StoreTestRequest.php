<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTestRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // We can set this to true for now, as authorization (e.g., must be logged in)
        // will be handled by route middleware later.
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'quran_text_id' => 'required|integer|exists:quran_texts,id',
            'wpm' => 'required|integer|min:0',
            'raw_wpm' => 'required|integer|min:0',
            'accuracy' => 'required|numeric|min:0|max:100',
            'char_count' => 'required|integer|min:0',
            'correct_chars' => 'required|integer|min:0',
            'incorrect_chars' => 'required|integer|min:0',
            'mode' => 'required|string|in:time,words,quote',
            'duration' => 'required|integer|min:1',
            'start_ayah' => 'required|integer|min:1',
            'end_ayah' => 'required|integer|min:1|gte:start_ayah',
            'total_errors' => 'required|integer|min:0',
        ];
    }
}
