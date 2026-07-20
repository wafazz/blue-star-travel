<?php

namespace App\Http\Controllers\Provider;

use App\Http\Controllers\Controller;

class DashboardController extends Controller
{
    public function index()
    {
        return view('provider.dashboard');
    }
}
