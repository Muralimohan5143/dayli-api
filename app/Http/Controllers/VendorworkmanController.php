<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class VendorworkmanController extends Controller
{
    public function showLogin()
    {
        return view('auth.vendor_workman_login');
    }
}
