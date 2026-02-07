<?php

namespace MatheusFS\Laravel\Insights\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AnalyzeLogsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorization should be handled by application middleware/policies
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'incident_data' => ['required', 'array'],
            'incident_data.timestamp' => ['required', 'array'],
            'incident_data.timestamp.started_at' => ['required', 'date'],
            'incident_data.timestamp.restored_at' => ['nullable', 'date'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'incident_data.required' => 'Dados do incidente são obrigatórios',
            'incident_data.timestamp.started_at.required' => 'Data de início do incidente é obrigatória',
            'incident_data.timestamp.restored_at.required' => 'Data de restauração é obrigatória para analisar incidentes fechados',
        ];
    }
}
