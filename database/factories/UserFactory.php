<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\Location;
use App\Models\DataUser;
/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => '$2y$10$hvDP.uUN65LIxe3uLxz.te0r/gsbFqOgabOKvKfSgiNYU4RWmW.wq', // asdasd
            'remember_token' => Str::random(10),
            'last_conection' => now(),
            'role_id' => function(){
                return rand(1,2);
            },
            'location_id' => function(){
                return Location::factory()->create()->id;
            },
            'data_user_id' => function(){
                return DataUser::factory()->create()->id;
            },
        ];
    }

    /**
     * Indicate that the model's role should be .
     *
     * @return static
     */
    public function barber()
    {
        return $this->state(fn (array $attributes) => [
            'email' => 'barber@test.com',
            'role_id' => 1
        ]);
    }
    public function client()
    {
        return $this->state(fn (array $attributes) => [
            'email' => 'client@test.com',
            'role_id' => 2
        ]);
    }

}
