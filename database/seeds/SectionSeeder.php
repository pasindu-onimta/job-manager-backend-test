<?php

use Illuminate\Database\Seeder;
use App\Section;

class SectionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $default = [
            ['section_name' => 'Back Log'],
            ['section_name' => 'Picked'],
            ['section_name' => 'Ongoing'],
            ['section_name' => 'Hold'],
            ['section_name' => 'QC'],
            ['section_name' => 'Finished'],
            ['section_name' => 'Bugs'],
            ['section_name' => 'Passed'],
        ];

        foreach ($default as $key => $value) {
            Section::create($value);
        }
    }
}
