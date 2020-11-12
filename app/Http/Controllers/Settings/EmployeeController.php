<?php

namespace App\Http\Controllers\Settings;

use App\Department;
use App\Employee;
use App\Http\Controllers\Controller;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EmployeeController extends Controller
{

    public function getdep()
    {
        $alldep = Department::all();
        return response()->json(['allitems' => $alldep], 200);
    }

    public function getPosition($id)
    {

        $posi = DB::table('emppositions')->where('Dep_id', $id)->get();
        return response()->json(['Position' => $posi], 200);
    }

    public function addemployee(Request $request)
    {

        //TODO: add form validation
        $employee = new Employee();
        $filename = 'default.png';

        if ($request->image) {
            $expl = explode(',', $request->image);
            $decode = base64_decode($expl[1]);

            if (Str::contains($expl[0], 'png')) {
                $exte = 'png';
            } else {
                $exte = 'jpg';
            }

            $currenttime = Carbon::now()->timestamp;
            $filename = $currenttime . '.' . $exte;
            $filepath = public_path() . '/Profileimage/' . $filename;
            file_put_contents($filepath, $decode);
        }

        $employee->Code = $request['code'];
        $employee->Email = $request['email'];
        $employee->FirstName = $request['fname'];
        $employee->LastName = $request['lname'];
        $employee->Mobile = $request['mobile'];
        $employee->Position_Id = $request['pos_id'];
        $employee->image = $filename;
        $employee->save();

        User::create([
            'employee_id' => $employee->id,
            'name' => $request['fname'] . ' ' . $request['lname'],
            'email' => $request['email'],
            // 'user_type' => 4,
            'password' => bcrypt('00000'),
        ]);

        return response()->json($employee, 201);
    }

    public function getEmployees()
    {

        $allempmod = new Employee();

        $allemp = $allempmod->listEmployee();
        return response()->json(['allemp' => $allemp], 200);
    }

    public function getEmployee($id)
    {
        $empmodel = new Employee();
        $employee = $empmodel->getemp($id);
        return $employee;
    }
}
