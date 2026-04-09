<?php

namespace App\Http\Controllers\Api;

use App\ApiClasses\Error;
use App\ApiClasses\Success;
use App\Http\Controllers\Controller;
use App\Models\Client;
use Constants;
use Illuminate\Http\Request;

class ClientController extends Controller
{
  public function getAllClients(Request $request)
  {
    $skip = $request->skip;
    $take = $request->take ?? 10;

    $clients = Client::query()
      ->where('status', 'active')
      ->orderBy('created_at', 'desc');

    if ($request->query('search')) {
      $clients->where('name', 'like', '%' . $request->query('search') . '%')
        ->orWhere('phone', 'like', '%' . $request->query('search') . '%')
        ->orWhere('email', 'like', '%' . $request->query('search') . '%')
        ->orWhere('contact_person_name', 'like', '%' . $request->query('search') . '%');
    }

    $totalCount = $clients->count();

    $clients = $clients->skip($skip)->take($take)->get();

    $finalClients = $this->getClients($clients);

    $response = [
      'totalCount' => $totalCount,
      'clients' => $finalClients
    ];

    return Success::response($response);
  }

  public function getClients($clients)
  {
    $finalClients = $clients->map(function ($client) {
      return [
        'id' => $client->id,
        'name' => $client->name,
        'address' => $client->address,
        'city' => $client->city,
        'contactPerson' => $client->contact_person,
        'email' => $client->email,
        'phoneNumber' => $client->phone,
        'latitude' => doubleval($client->latitude),
        'longitude' => doubleval($client->longitude),
        'status' => $client->status,
        'createdAt' => $client->created_at->format(Constants::DateTimeFormat),
        'updatedAt' => $client->updated_at->format(Constants::DateTimeFormat),
      ];
    });
    return $finalClients;
  }

  public function search(Request $request)
  {
    $query = $request->query('query');

    if (!$query) {
      return Error::response('Query is required');
    }

    $clients = Client::where('status', 'active')
      ->where('name', 'like', '%' . $query . '%')
      ->orWhere('phone', 'like', '%' . $query . '%')
      ->orWhere('email', 'like', '%' . $query . '%')
      ->orWhere('contact_person_name', 'like', '%' . $query . '%')
      ->orWhere('address', 'like', '%' . $query . '%')
      ->take(10)
      ->get();

    $finalClients = $this->getClients($clients);

    return Success::response($finalClients);
  }

  public function create(Request $request)
  {

    $name = $request->name;
    $address = $request->address;
    $latitude = $request->latitude;
    $longitude = $request->longitude;
    $phoneNumber = $request->phoneNumber;
    $contactPerson = $request->contactPerson;
    $email = $request->email;
    $city = $request->city;
    $remarks = $request->remarks;

    if ($name == null) {
      return Error::response('Name is required');
    }

    if ($address == null) {
      return Error::response('Address is required');
    }

    if ($latitude == null) {
      return Error::response('Latitude is required');
    }

    if ($longitude == null) {
      return Error::response('Longitude is required');
    }

    if ($phoneNumber == null) {
      return Error::response('Phone Number is required');
    }

    if ($contactPerson == null) {
      return Error::response('Contact Person is required');
    }

    if ($city == null) {
      return Error::response('City is required');
    }

    Client::create([
      'name' => $name,
      'address' => $address,
      'latitude' => $latitude,
      'longitude' => $longitude,
      'phone' => $phoneNumber,
      'contact_person' => $contactPerson,
      'email' => $email,
      'city' => $city,
      'remarks' => $remarks,
      'created_by_id' => auth()->user()->id,
    ]);

    return Success::response('Client created successfully');
  }
}
