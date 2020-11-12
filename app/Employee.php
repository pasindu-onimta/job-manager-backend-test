<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Employee extends Model
{
    protected $guarded = [];
    public function user()
    {
        return $this->belongsTo('App\User');
    }
    
    public function getemp($id)
    {

        $emp = DB::select('SELECT E.*,P.Position,P.Dep_id FROM employees E inner join emppositions P on E.Position_Id=P.id where E.id='.$id);
        return $emp;
    }


    public function listEmployee(){

        $elist = DB::select('SELECT E.*,P.Position FROM employees E inner join emppositions P on E.Position_Id=P.id order by E.id');
        return $elist;


    }
}
