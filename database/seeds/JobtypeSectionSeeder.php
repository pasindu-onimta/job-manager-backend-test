<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class JobtypeSectionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $default = [
            ['jobtype_id' => 1, 'section_id' => 1],
            ['jobtype_id' => 1, 'section_id' => 2],
            ['jobtype_id' => 1, 'section_id' => 3],
            ['jobtype_id' => 1, 'section_id' => 4],
            ['jobtype_id' => 1, 'section_id' => 5],
            ['jobtype_id' => 1, 'section_id' => 6],
            ['jobtype_id' => 2, 'section_id' => 1],
            ['jobtype_id' => 2, 'section_id' => 2],
            ['jobtype_id' => 2, 'section_id' => 3],
            ['jobtype_id' => 2, 'section_id' => 4],
            ['jobtype_id' => 2, 'section_id' => 7],
            ['jobtype_id' => 2, 'section_id' => 8],
        ];

        foreach ($default as $key => $value) {
            DB::table('jobtype_section')->insert($value);
        }
    }
}
