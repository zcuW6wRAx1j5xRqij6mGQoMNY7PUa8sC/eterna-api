<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ChangeKlineTypeRequest extends FormRequest {
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
			'coin_id'   => 'required|exists:symbols,id',
			// 'coin_type' => 'bail|required|in:spot,futures',
			'type'      => 'required|in:1m,5m,15m,30m,1h,1d',
		];
	}
}
