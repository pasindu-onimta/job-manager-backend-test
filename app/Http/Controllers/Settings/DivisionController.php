<?php

namespace App\Http\Controllers\Settings;

use App\Division;
use App\Http\Controllers\Controller;

class DivisionController extends Controller
{
    //

    public function index()
    {
        return Division::all();
    }
}
