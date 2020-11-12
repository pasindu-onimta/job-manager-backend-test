<?php

use Illuminate\Database\Seeder;
use App\Status;

class StatusTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $default = [
            ['id' => 1, 'status' => 'Active'],
            ['id' => 6, 'status' => 'Finished'],
        ];

        foreach ($default as $key => $value) {
            Status::create($value);
        }
    }
}
