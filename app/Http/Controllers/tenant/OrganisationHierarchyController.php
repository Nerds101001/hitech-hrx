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
            // Root is the reporting manager (One level up)
            $rootUsers = $users->filter(fn($u) => $u->id === $managerId);
        } else {
            // If user has no senior, they see the whole company (All top-level roots)
            // This satisfies "if he is the senior most then whole company"
            $rootUsers = $users->filter(fn($u) => $u->reporting_to_id === null);
        }
    }

    $hierarchy = [];
    $isStaffOnly = $user->hasRole('employee') && !$user->hasRole(['admin', 'hr', 'manager']);
    $maxDepth = $isStaffOnly ? 1 : null;

    foreach ($rootUsers as $root) {
        $hierarchy[] = $this->formatUserNode($root, $users, 0, $maxDepth);
    }

    return view('tenant.organisation-hierarchy.index', [
      'pageConfigs' => ['contentLayout' => 'wide'],
      'hierarchy' => $hierarchy
    ]);
  }

  private function formatUserNode($user, $allUsers, $depth = 0, $maxDepth = null)
  {
    $children = [];
    
    // If maxDepth is set, don't go beyond it
    if ($maxDepth === null || $depth < $maxDepth) {
        foreach ($allUsers as $u) {
            if ($u->reporting_to_id == $user->id) {
                $children[] = $this->formatUserNode($u, $allUsers, $depth + 1, $maxDepth);
            }
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
