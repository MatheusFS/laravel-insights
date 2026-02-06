<?php

namespace MatheusFS\Laravel\Insights\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApplyWAFRulesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Ação destrutiva - application deve implementar authorization
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'web_acl_id' => ['required', 'string'],
            'web_acl_name' => ['required', 'string'],
            'auto_apply' => ['nullable', 'boolean'],
            'dry_run' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'web_acl_id.required' => 'ID do Web ACL é obrigatório',
            'web_acl_name.required' => 'Nome do Web ACL é obrigatório',
        ];
    }
}
