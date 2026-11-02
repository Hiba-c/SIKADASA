<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'registration_code' => $this->registration_code,
            'full_name' => $this->full_name,
            'address' => $this->address,
            'house_number' => $this->house_number,
            'phone' => $this->phone,
            'joined_at' => $this->joined_at?->toDateString(),
            'notes' => $this->notes,
            'status' => $this->status->value,
            'inactive_at' => $this->inactive_at?->toIso8601String(),
            'inactive_reason' => $this->inactive_reason,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}