<?php

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // $this->call(EmpPositionSeeder::class);
        $this->call([SectionSeeder::class, JobtypeSeeder::class, JobtypeSectionSeeder::class, EmpPositionSeeder::class]);
    }
}
