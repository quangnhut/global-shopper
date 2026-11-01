<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Shopper\Core\Enum\AddressType;
use Shopper\Core\Enum\GenderType;
use Shopper\Core\Models\Address;
use Shopper\Core\Models\Country;

final class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $userModel = config('auth.providers.users.model');
        $countries = Country::query()->whereIn('cca2', ['US', 'CA', 'GB', 'FR', 'DE', 'VN'])->get();
        $password = Hash::make('password');

        for ($i = 0; $i < 30; $i++) {
            $gender = fake()->randomElement(GenderType::values());
            $createdAt = now()->subDays(fake()->numberBetween(1, 365));

            $customer = $userModel::query()->create([
                'first_name' => fake()->firstName($gender),
                'last_name' => fake()->lastName(),
                'email' => fake()->unique()->safeEmail(),
                'gender' => $gender,
                'phone_number' => fake()->phoneNumber(),
                'birth_date' => fake()->dateTimeBetween('-60 years', '-18 years'),
                'password' => $password,
                'email_verified_at' => $createdAt,
                'opt_in' => fake()->boolean(),
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            $customer->assignRole(config('shopper.admin.roles.user'));

            foreach (AddressType::cases() as $type) {
                Address::query()->create([
                    'user_id' => $customer->id,
                    'first_name' => $customer->first_name,
                    'last_name' => $customer->last_name,
                    'street_address' => fake()->streetAddress(),
                    'postal_code' => fake()->postcode(),
                    'city' => fake()->city(),
                    'phone_number' => $customer->phone_number,
                    'country_id' => $countries->random()->id,
                    'type' => $type,
                    'shipping_default' => $type === AddressType::Shipping,
                    'billing_default' => $type === AddressType::Billing,
                ]);
            }
        }
    }
}
