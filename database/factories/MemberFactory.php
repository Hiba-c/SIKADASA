<?php

namespace Database\Factories;

use App\Enums\MemberStatus;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Member>
 */
class MemberFactory extends Factory
{
    protected $model = Member::class;

    public function definition(): array
    {
        return [
            'registration_code' => 'DWS-'.fake()->unique()->numerify('######'),
            'full_name' => fake()->name(),
            'address' => fake()->address(),
            'house_number' => fake()->buildingNumber(),
            'phone' => fake()->phoneNumber(),
            'joined_at' => fake()->date(),
            'status' => MemberStatus::Active,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => MemberStatus::Inactive,
            'inactive_at' => now(),
            'inactive_reason' => fake()->sentence(),
        ]);
    }
}