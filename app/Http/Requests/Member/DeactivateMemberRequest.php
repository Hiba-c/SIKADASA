<?php

namespace App\Http\Requests\Member;

use App\Models\Member;
use Illuminate\Foundation\Http\FormRequest;

class DeactivateMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        $member = $this->route('member');

        return $member instanceof Member && ($this->user()?->can('changeStatus', $member) ?? false);
    }

    public function rules(): array
    {
        return [
            'inactive_reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
