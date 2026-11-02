<?php

namespace Database\Factories;

use App\Enums\LoanStatus;
use App\Models\Loan;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Loan>
 */
class LoanFactory extends Factory
{
    protected $model = Loan::class;

    public function definition(): array
    {
        $principal = fake()->numberBetween(100, 1000) * 10000;
        $service = round($principal * 0.005, 2);

        return [
            'loan_number' => 'PJM-'.fake()->unique()->numerify('######'),
            'member_id' => Member::factory(),
            'principal_amount' => $principal,
            'service_rate' => 0.5,
            'service_amount' => $service,
            'total_obligation' => $principal + $service,
            'installment_count' => 5,
            'application_date' => fake()->date(),
            'status' => LoanStatus::Pending,
        ];
    }
}