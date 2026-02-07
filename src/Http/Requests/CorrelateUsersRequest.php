<?php

namespace MatheusFS\Laravel\Insights\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CorrelateUsersRequest extends FormRequest
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
            'start_time' => ['required', 'date'],
            'end_time' => ['required', 'date', 'after:start_time'],
            'force' => ['sometimes', 'boolean'], // Permitir forçar re-correlação
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'start_time.required' => 'Data de início é obrigatória',
            'end_time.required' => 'Data de fim é obrigatória',
            'end_time.after' => 'Data de fim deve ser posterior ao início',
        ];
    }
}
