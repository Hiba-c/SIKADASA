<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrganizationSetting extends Model
{
    protected $fillable = [
        'organization_name', 'address', 'logo_path', 'chairman_name',
        'treasurer_name', 'phone', 'email', 'arisan_contribution_amount',
        'loan_service_rate', 'receipt_footer',
    ];

    protected function casts(): array
    {
        return [
            'arisan_contribution_amount' => 'decimal:2',
            'loan_service_rate' => 'decimal:2',
        ];
    }
}