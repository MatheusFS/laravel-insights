<?php

namespace MatheusFS\Laravel\Insights\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateWAFRulesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'ip_set_name' => ['nullable', 'string', 'max:128'],
            'web_acl_id' => ['nullable', 'string'],
        ];
    }
}
