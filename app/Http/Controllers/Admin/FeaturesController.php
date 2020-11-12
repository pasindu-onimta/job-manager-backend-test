<?php

namespace App\Http\Controllers\Admin;

use App\Feature;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class FeaturesController extends Controller
{
    public function allFeatures()
    {
        $features = Feature::all();
        return response()->json($features, 200);
    }

    public function changeFeatureStatus(Request $request)
    {
        $features = $request->features;
        foreach ($features as $feature) {
            Feature::find($feature['id'])->update(['status' => $feature['status']]);
        }
        return response()->json(Feature::all(), 201);
    }
}
