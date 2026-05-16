<?php

namespace App\Http\Controllers;

use App\Models\Plan;

class PricingController extends Controller
{
    public function index()
    {
        $plans = Plan::active()->ordered()->get();
        return view('pricing', compact('plans'));
    }
}
