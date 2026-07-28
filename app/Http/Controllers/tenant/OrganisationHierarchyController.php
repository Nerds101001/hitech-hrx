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

    // Split the roots into the three bands the chart renders separately:
    // Directors and GM-level are leadership (shown as cards at the top), and
    // anyone else with no reporting manager goes into an explicit
    // "No Manager" band rather than floating as a stray root in the tree.
    $directors = $gms = $unassigned = [];
    foreach ($hierarchy as $node) {
        switch ($this->leadershipTier($node)) {
            case 'director': $directors[]  = $node; break;
            case 'gm':       $gms[]        = $node; break;
            default:         $unassigned[] = $node; break;
        }
    }

    // Only real reporting lines belong in the chart itself.
    $treeRoots = array_merge($directors, array_filter($gms, fn($n) => !empty($n['children'])));

    usort($directors,  fn($a, $b) => $this->countTeam($b) <=> $this->countTeam($a));
    usort($gms,        fn($a, $b) => strcmp($a['name'], $b['name']));
    usort($unassigned, fn($a, $b) => strcmp($a['name'], $b['name']));

    return view('tenant.organisation-hierarchy.index', [
      'pageConfigs' => ['contentLayout' => 'wide'],
      'hierarchy'   => $hierarchy,
      'directors'   => $directors,
      'gms'         => $gms,
      'unassigned'  => $unassigned,
      'treeRoots'   => $treeRoots,
      'totalPeople' => $this->countNodes($hierarchy),
      'managerCount'=> $this->countManagers($hierarchy),
      'deptCount'   => $users->pluck('department.name')->filter()->unique()->count(),
    ]);
  }

  /** Director / GM-level / everyone else, from designation and account name. */
  private function leadershipTier(array $node): string
  {
    $title = strtolower($node['designation'] ?? '');
    $name  = strtolower($node['name'] ?? '');

    if (str_contains($title, 'director')) {
        return 'director';
    }
    if (str_starts_with($name, 'gm') || str_contains($title, 'general manager')) {
        return 'gm';
    }
    return 'other';
  }

  private function countNodes(array $nodes): int
  {
    return array_sum(array_map(fn($n) => 1 + $this->countNodes($n['children'] ?? []), $nodes));
  }

  private function countTeam(array $node): int
  {
    return $this->countNodes($node['children'] ?? []);
  }

  private function countManagers(array $nodes): int
  {
    $n = 0;
    foreach ($nodes as $node) {
        if (!empty($node['children'])) {
            $n++;
            $n += $this->countManagers($node['children']);
        }
    }
    return $n;
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
