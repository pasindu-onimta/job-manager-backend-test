<?php

use Illuminate\Database\Seeder;
use App\UserType;

class UserTypeTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $default = [
            ['type' => 'Super Admin'], 
            ['type' => 'Admin'], 
            ['type' =>'Director'], 
            ['type' =>'Department Head'], 
            ['type' =>'Group Leader'],
            ['type' =>'Developer']
        ];

        foreach ($default as $key => $value) {
            UserType::create($value);
        }
    }
}
