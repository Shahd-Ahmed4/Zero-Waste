<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        $names = [
            'Ahmed Mohamed',
            'Omar Ali',
            'Youssef Hassan',
            'Menna Ahmed',
            'Nour Mohamed',
            'Salma Khaled',
            'Hassan Mahmoud',
            'Karim Tarek',
            'Aya Samy',
            'Mostafa Ali',
            'Mariam Adel',
            'Heba Samir'
        ];

        $addresses = [
            'Nasr City, Cairo',
            'Maadi, Cairo',
            'Heliopolis, Cairo',
            'Dokki, Giza',
            'Sheikh Zayed, Giza',
            'New Cairo'
        ];

        return [
            'name' => fake()->randomElement($names),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('12345678'),
            'remember_token' => Str::random(10),

            'phone' => '01' . rand(000000000, 999999999),
            'address' => fake()->randomElement($addresses),

            'role' => 'customer',
            'status' => 'active',
            'accepted_terms' => true,
        ];
    }
}