<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $presence = $this->isMethod('put') ? 'required' : 'sometimes';

        return [
            'title' => [$presence, 'required', 'string', 'max:200'],
            'content' => [$presence, 'required', 'string', 'max:10000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if (! $this->exists('title') && ! $this->exists('content')) {
                $validator->errors()->add('note', 'Provide at least one of title or content.');
            }
        }];
    }
}
