<?php

use App\Feature;
use Illuminate\Database\Seeder;

class FeatureSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $default = [
            ['feature' => 'every task should have authorized person', 'status' => 0]
        ];

        foreach ($default as $key => $value) {
            Feature::create($value);
        }
    }
}
