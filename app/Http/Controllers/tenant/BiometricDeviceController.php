<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use App\Models\BiometricDevice;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class BiometricDeviceController extends Controller
{
    public function index()
    {
        if (Auth::user()->can('Manage Attendance')) {
            $devices = BiometricDevice::all();
            return view('tenant.biometric.index', compact('devices'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function create()
    {
        if (Auth::user()->can('Manage Attendance')) {
            $sites = Site::all()->pluck('name', 'id');
            return view('tenant.biometric.create', compact('sites'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function store(Request $request)
    {
        if (Auth::user()->can('Manage Attendance')) {
            $validator = Validator::make($request->all(), [
                'name' => 'required',
                'ip_address' => 'required',
                'port' => 'required|numeric',
                'site_id' => 'required',
            ]);

            if ($validator->fails()) {
                return redirect()->back()->withErrors($validator)->withInput();
            }

            BiometricDevice::create([
                'name' => $request->name,
                'ip_address' => $request->ip_address,
                'port' => $request->port,
                'site_id' => $request->site_id,
                'is_active' => $request->has('is_active'),
            ]);

            return redirect()->route('biometric.index')->with('success', __('Device created successfully.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function edit(BiometricDevice $biometric)
    {
        if (Auth::user()->can('Manage Attendance')) {
            $sites = Site::all()->pluck('name', 'id');
            return view('tenant.biometric.edit', compact('biometric', 'sites'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function update(Request $request, BiometricDevice $biometric)
    {
        if (Auth::user()->can('Manage Attendance')) {
            $validator = Validator::make($request->all(), [
                'name' => 'required',
                'ip_address' => 'required',
                'port' => 'required|numeric',
                'site_id' => 'required',
            ]);

            if ($validator->fails()) {
                return redirect()->back()->withErrors($validator)->withInput();
            }

            $biometric->update([
                'name' => $request->name,
                'ip_address' => $request->ip_address,
                'port' => $request->port,
                'site_id' => $request->site_id,
                'is_active' => $request->has('is_active'),
            ]);

            return redirect()->route('biometric.index')->with('success', __('Device updated successfully.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function destroy(BiometricDevice $biometric)
    {
        if (Auth::user()->can('Manage Attendance')) {
            $biometric->delete();
            return redirect()->route('biometric.index')->with('success', __('Device deleted successfully.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Test connection to the device
     */
    public function testConnection(Request $request)
    {
        $ip = $request->ip_address;
        $port = $request->port;

        // Simple socket check for port forwarding
        $connection = @fsockopen($ip, $port, $errno, $errstr, 5);

        if (is_resource($connection)) {
            fclose($connection);
            return response()->json(['status' => 'success', 'message' => __('Device is reachable!')]);
        } else {
            return response()->json(['status' => 'error', 'message' => __('Cannot connect to device: ') . $errstr]);
        }
    }
}
