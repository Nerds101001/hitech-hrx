@php
    $title = 'Client Details'
@endphp
@section('title', __($title))

@extends('layouts/layoutMaster')

  @section('content')
    <div class="row mb-3">
        <div class="col">
            <div class="float-start">
                <h4 class="mt-2">{{$title}}</h4>
            </div>
        </div>
        <div class="col">

        </div>
    </div>
<div class="card mt-2">
    <div class="card-body">
        <div class="row">
            <div class="col">
                <table class="table table-bordered">
                    <tr>
                        <th>Client Name</th>
                        <td>{{$client->name}}</td>
                    </tr>
                    <tr>
                        <th>Phone Number</th>
                        <td>{{$client->phone}}</td>
                    </tr>
                    <tr>
                        <th>Email</th>
                        <td>{{$client->email}}</td>
                    </tr>
                    <tr>
                        <th>Address</th>
                        <td>{{$client->address}}</td>
                    </tr>
                    <tr>
                        <th>City</th>
                        <td>{{$client->city ?? 'N/A'}}</td>
                    </tr>
                    <tr>
                        <th>Contact Person</th>
                        <td>{{$client->contact_person_name ?? 'N/A'}}</td>
                    </tr>
                    <tr>
                        <th>Remarks</th>
                        <td>{{$client->remarks ?? 'N/A'}}</td>
                    </tr>
                    <tr>
                        <th>Created At</th>
                        <td>{{$client->created_at}}</td>
                    </tr>
                    <tr>
                        <th>Updated At</th>
                        <td>{{$client->updated_at}}</td>
                    </tr>
                    <tr>
                        <th>Status</th>
                        <td>
                            @if($client->status == 'active')
                                <span class="badge bg-success">Active</span>
                            @else
                                <span class="badge bg-danger">Inactive</span>
                            @endif
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
