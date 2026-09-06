<?php

namespace App\Http\Requests\Race;

use Illuminate\Foundation\Http\FormRequest;

class FinishRaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'chars' => ['required', 'integer', 'min:1', 'max:100000'],
            'correct_chars' => ['required', 'integer', 'min:0', 'lte:chars'],
        ];
    }
}
