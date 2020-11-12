<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Jobtype;

class JobtypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $default = [
            ['type' => 'dev'],
            ['type' => 'qc'],
        ];

        foreach ($default as $key => $value) {
            Jobtype::create($value);
        }
    }
}
