<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use App\Models\User;

class OrganisationHierarchyController extends Controller
{
  public function index()
  {
    $user = auth()->user();
    $users = User::with(['reportingTo', 'department', 'designation'])->get();

    // entry point users
    $rootUsers = [];

    // Show the whole company to everyone (All top-level roots)
    // Satisfies "show all staff to all no restriction in hierarchy"
    $rootUsers = $users->filter(fn($u) => $u->reporting_to_id === null);

    $hierarchy = [];
    // Removed maxDepth restriction for employees as requested: "no restriction in hierarchy show it to all"
    $maxDepth = null; 

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
    
    if ($maxDepth === null || $depth < $maxDepth) {
        foreach ($allUsers as $u) {
            if ($u->reporting_to_id == $user->id) {
                $children[] = $this->formatUserNode($u, $allUsers, $depth + 1, $maxDepth);
            }
        }

        // Sort by department first, then by name — keeps same-dept people adjacent
        usort($children, function($a, $b) {
            $deptCompare = strcmp($a['department'], $b['department']);
            if ($deptCompare !== 0) return $deptCompare;
            return strcmp($a['name'], $b['name']);
        });
    }

    return [
      'id'              => $user->id,
      'name'            => $user->getFullName(),
      'code'            => $user->code ?? 'N/A',
      'designation'     => $user->designation?->name ?? 'Staff Member',
      'department'      => $this->getDepartmentName($user),
      'email'           => $user->email,
      'phone'           => $user->phone ?? 'N/A',
      'profile_picture' => $user->getProfilePicture(),
      'initials'        => $user->getInitials(),
      'status'          => 'online',
      'children'        => $children,
    ];
  }

  private function buildHierarchy($users, $parentId = null)
  {
    $result = [];
    foreach ($users as $user) {
      if ($user->reporting_to_id == $parentId) {
        $children = $this->buildHierarchy($users, $user->id);
        
        $designation = $user->designation?->name ?? 'Staff Member';
        $department = $this->getDepartmentName($user);
        
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

  private function getDepartmentName($user): string
  {
    return $user->department?->name
      ?? 'No Department Assigned';
  }

}
