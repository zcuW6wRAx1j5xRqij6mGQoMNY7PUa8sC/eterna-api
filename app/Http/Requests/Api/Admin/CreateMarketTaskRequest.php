<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CreateMarketTaskRequest extends FormRequest {

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
			'coin_id'      => 'bail|required|exists:symbols,id',
			'coin_type'    => 'bail|required|in:spot,futures',
			'close'        => 'bail|required|decimal:0,5',
			'close_offset' => 'bail|required|decimal:0,5',
			'target_max'   => 'bail|required|decimal:0,5',
			'target_min'   => 'bail|required|decimal:0,5',
			'rate_max'     => 'bail|required|decimal:0,5',
			'rate_min'     => 'bail|required|decimal:0,5',
		];
	}
}
