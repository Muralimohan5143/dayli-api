<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Lead;

class LeadController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name'       => 'required|string|max:255',
            'last_name'        => 'nullable|string|max:255',
            'phone'            => 'required|string|max:15',
            'alternate_phone'  => 'nullable|string|max:15',
            'email'            => 'nullable|email',
            'lang_locale'      => 'nullable|string|max:50',
            'address1'         => 'nullable|string|max:255',
            'address2'         => 'nullable|string|max:255',
            'city'             => 'nullable|string|max:100',
            'state'            => 'nullable|string|max:100',
            'pincode'          => 'nullable|string|max:10',
            'lead_type'        => 'nullable|string|max:50',
            'zone'             => 'nullable|string|max:100',
            'source'           => 'nullable|string|max:100',
            'collected_by'     => 'nullable|string|max:100',
            'notes'            => 'nullable|string',
            'status'           => 'nullable|string|max:50',
            'follow_up_date'   => 'nullable|date',
            'collected_lat'    => 'nullable|numeric',
            'collected_lng'    => 'nullable|numeric',
        ]);

        Lead::create($validated);

        return redirect()->route('leads.success')->with('message', 'Lead successfully created!');
    }
}
