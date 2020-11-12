<?php

use Illuminate\Database\Seeder;
use App\Setting;

class SettingsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $default = [
            ['id_type' => 1, 'type_name' => 'Quick Job', 'last_id' => 0],
            ['id_type' => 2, 'type_name' => 'Tasking', 'last_id' => 0],
            ['id_type' => 3, 'type_name' => 'Internal Office Works', 'last_id' => 0],
        ];

        foreach ($default as $key => $value) {
            Setting::create($value);
        }
    }
}
