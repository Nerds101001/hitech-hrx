<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use App\Models\User;

class OrganisationHierarchyController extends Controller
{
  public function index()
  {
    $user = auth()->user();
    $users = User::with(['reportingTo', 'designation.department'])->get();

    // entry point users
    $rootUsers = [];

    if ($user->hasRole(['admin', 'hr'])) {
        $rootUsers = $users->filter(fn($u) => $u->reporting_to_id === null);
    } else {
        $managerId = $user->reporting_to_id;
        if ($managerId) {
            // Root is the reporting manager
            $rootUsers = $users->filter(fn($u) => $u->id === $managerId);
        } else {
            // If no manager, user is the root
            $rootUsers = $users->filter(fn($u) => $u->id === $user->id);
        }
    }

    $hierarchy = [];
    foreach ($rootUsers as $root) {
        $hierarchy[] = $this->formatUserNode($root, $users);
    }

    return view('tenant.organisation-hierarchy.index', [
      'pageConfigs' => ['contentLayout' => 'wide'],
      'hierarchy' => $hierarchy
    ]);
  }

  private function formatUserNode($user, $allUsers)
  {
    $children = [];
    foreach ($allUsers as $u) {
        if ($u->reporting_to_id == $user->id) {
            $children[] = $this->formatUserNode($u, $allUsers);
        }
    }

    return [
      'id' => $user->id,
      'name' => $user->getFullName(),
      'code' => $user->code ?? 'N/A',
      'designation' => $user->designation?->name ?? 'Staff Member',
      'department' => $user->designation?->department?->name ?? 'General Department',
      'email' => $user->email,
      'phone' => $user->phone ?? 'N/A',
      'profile_picture' => $user->getProfilePicture(),
      'initials' => $user->getInitials(),
      'status' => 'online',
      'children' => $children,
    ];
  }

  private function buildHierarchy($users, $parentId = null)
  {
    $result = [];
    foreach ($users as $user) {
      if ($user->reporting_to_id == $parentId) {
        $children = $this->buildHierarchy($users, $user->id);
        
        $designation = $user->designation?->name ?? 'Staff Member';
        $department = $user->designation?->department?->name ?? 'General Department';
        
        $result[] = [
          'id' => $user->id,
          'name' => $user->getFullName(),
          'code' => $user->code ?? 'N/A',
          'designation' => $designation,
          'department' => $department,
          'email' => $user->email,
          'phone' => $user->phone ?? 'N/A',
          'profile_picture' => $user->getProfilePicture(),
          'initials' => $user->getInitials(),
          'status' => 'online', // Mocking online status
          'children' => $children,
        ];
      }
    }
    return $result;
  }

}
