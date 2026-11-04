<?php

namespace App\Http\Requests\IdCards;

use Illuminate\Foundation\Http\FormRequest;

class RevokeIdCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('id_cards.revoke');
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
