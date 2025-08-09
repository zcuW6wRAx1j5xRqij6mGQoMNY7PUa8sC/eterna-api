<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CreateMarketTaskRequest extends FormRequest {
    
    protected $stopOnFirstFailure = true;
    
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
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
            'coin_id'    => 'required|exists:symbols,id',
            'coin_type'  => 'required|in:spot,futures',
            'open'       => 'required|decimal:0,5',
            'close'      => 'required|decimal:0,5',
            'high'       => 'required|decimal:0,5',
            'low'        => 'required|decimal:0,5',
            'start_time' => 'required|date_format:Y-m-d H:i',
            'end_time'   => 'required|date_format:Y-m-d H:i',
        ];
    }
}
