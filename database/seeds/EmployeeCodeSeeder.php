<?php

use App\Employee;
use Illuminate\Database\Seeder;

class EmployeeCodeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $emp_codes = [
            '105' => '0678',
            '104' => 'E123',
            '60' => 'EMP-321',
            '86' => '0656',
            '115' => '0684',
            '49' => '0632',
            '14' => '0538',
            '106' => '0681',
            '16' => '0515',
            '12' => '0521',
            '50' => '0633',
            '79' => '0647',
            '83' => '0654',
            '27' => '0500',
            '65' => '0683',
            '100' => '0673',
            '67' => '0643',
            '35' => '0023',
            '90' => '0664',
            '59' => '0638',
        ];

        $employees = Employee::all();

        foreach ($employees as $key => $employee) {

            $results = array_search($employee->Code, $emp_codes, true);
            if ($results) {
                $employee->where('Code', $employee->Code)->update(['emp_code' => $results]);
            }
        }
    }
}
