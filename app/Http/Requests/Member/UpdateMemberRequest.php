<?php

namespace App\Http\Requests\Member;

use App\Models\Member;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        $member = $this->route('member');

        return $member instanceof Member && ($this->user()?->can('update', $member) ?? false);
    }

    public function rules(): array
    {
        $member = $this->route('member');

        return [
            'full_name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:2000'],
            'house_number' => ['nullable', 'string', 'max:30'],
            'phone' => ['nullable', 'string', 'max:30'],
            'joined_at' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
