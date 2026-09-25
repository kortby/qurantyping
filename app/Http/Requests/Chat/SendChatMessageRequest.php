<?php

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;

class SendChatMessageRequest extends FormRequest
{
    /**
     * Only accepted friends may message each other.
     */
    public function authorize(): bool
    {
        return $this->user() !== null
            && $this->route('user') !== null
            && $this->user()->isFriendsWith($this->route('user'));
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.required' => __('Write a message first.'),
            'body.max' => __('Messages are limited to 2000 characters.'),
        ];
    }

    /**
     * Trim surrounding whitespace so blank messages fail `required`.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->body)) {
            $this->merge(['body' => trim($this->body)]);
        }
    }
}
