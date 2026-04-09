<?php

namespace App\Http\Controllers\tenant;

use App\Api\Shared\Responses\Success;
use App\Http\Controllers\Controller;
use App\Models\Client;
//use Request;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function index()
    {
        $clients = Client::all();

        return view('tenant.client.index', compact('clients'));
    }

    public function show($id)
    {
        $client = Client::find($id);

        return view('tenant.client.show', compact('client'));
    }

    public function create()
    {
        return view('tenant.client.create');
    }

    public function store(Request $request)
    {

        $validated = $request->validate([
            'name' => 'required|max:255',
            'email' => 'required|max:255',
            'address' => 'required|max:255',
            'phone' => 'required|max:255',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'contactPersonName' => 'nullable|max:255',
            'radius' => 'required|numeric',
            'city' => 'nullable |max:255',
            'remarks' => 'nullable|max:255',
        ]);

        $client = new Client();
        $client->name = $validated['name'];
        $client->email = $validated['email'];
        $client->address = $validated['address'];
        $client->phone = $validated['phone'];
        $client->latitude = $validated['latitude'];
        $client->longitude = $validated['longitude'];
        $client->contact_person_name = $validated['contactPersonName'];
        $client->radius = $validated['radius'];
        $client->city = $validated['city'];
        $client->remarks = $validated['remarks'];
        $client->created_by_id = auth()->id();
        $client->save();

        return redirect()->route('client.index')->with('success', 'Client created successfully');
    }

    public function edit($id)
    {
        $client = Client::findOrFail($id);

        return view('tenant.client.edit', compact('client'));
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'required|max:255',
            'email' => 'required|max:255',
            'address' => 'required|max:255',
            'phone' => 'required|max:255',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'contactPersonName' => 'nullable|max:255',
            'radius' => 'required|numeric',
            'city' => 'nullable |max:255',
            'remarks' => 'nullable|max:255',
        ]);

        $client = Client::findOrFail($id);
        $client->name = $validated['name'];
        $client->email = $validated['email'];
        $client->address = $validated['address'];
        $client->phone = $validated['phone'];
        $client->latitude = $validated['latitude'];
        $client->longitude = $validated['longitude'];
        $client->contact_person_name = $validated['contactPersonName'];
        $client->radius = $validated['radius'];
        $client->city = $validated['city'];
        $client->remarks = $validated['remarks'];
        $client->updated_by_id = auth()->id();
        $client->save();

        return redirect()->route('client.index')->with('success', 'Client updated successfully');
    }

    public function changeStatus()
    {
        $client = Client::find(request()->id);
        $client->status = $client->status == 'active' ? 'inactive' : 'active';
        $client->save();

        return response()->json('Status Updated Successfully.');
    }

}
