<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use App\Models\User;

class OrganisationHierarchyController extends Controller
{
  public function index()
  {
    $user = auth()->user();
    // Terminated staff must never appear in the org chart — they are not part
    // of the organisation any more, and leaving them in also drags their whole
    // former sub-tree along with them.
    $users = User::notTerminated()
      ->with(['reportingTo', 'department', 'designation'])
      ->get();

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

    usort($directors,  fn($a, $b) => $this->countTeam($b) <=> $this->countTeam($a));
    usort($gms,        fn($a, $b) => strcmp($a['name'], $b['name']));
    usort($unassigned, fn($a, $b) => strcmp($a['name'], $b['name']));

    // The directors belong INSIDE the chart, not in a banner above it, so the
    // whole company reads as one tree: company -> directors -> everyone else.
    $companyName = optional(\App\Models\Settings::first())->company_name ?: 'Hi Tech Group';
    $companyRoot = [
      'id'              => null,
      'name'            => $companyName,
      'code'            => '',
      'designation'     => 'Organisation',
      'department'      => '',
      'email'           => '',
      'phone'           => '',
      'profile_picture' => null,
      'initials'        => $this->initialsFor($companyName),
      'status'          => 'online',
      'is_org'          => true,
      'children'        => array_merge(
          $directors,
          // GM-level accounts that actually have reports sit beside the
          // directors; the ones without are listed in the GM band instead.
          array_values(array_filter($gms, fn($n) => !empty($n['children'])))
      ),
    ];

    // Department colour map, shared by the chart and the legend so a person's
    // border colour always matches their department swatch.
    $deptColour = $this->departmentColours($hierarchy);

    return view('tenant.organisation-hierarchy.index', [
      'pageConfigs' => ['contentLayout' => 'wide'],
      'hierarchy'   => $hierarchy,
      'directors'   => $directors,
      'gms'         => $gms,
      // Only the GMs with no team need listing separately — the rest are in the tree.
      'gmsWithoutReports' => array_values(array_filter($gms, fn($n) => empty($n['children']))),
      'unassigned'  => $unassigned,
      'treeRoots'   => [$companyRoot],
      'deptColour'  => $deptColour,
      'companyName' => $companyName,
      'totalPeople' => $this->countNodes($hierarchy),
      'managerCount'=> $this->countManagers($hierarchy),
      'deptCount'   => $users->pluck('department.name')->filter()->unique()->count(),
    ]);
  }

  private function initialsFor(string $name): string
  {
    $words = preg_split('/\s+/', trim($name)) ?: [];
    $ini = '';
    foreach (array_slice($words, 0, 2) as $w) {
        $ini .= mb_strtoupper(mb_substr($w, 0, 1));
    }
    return $ini !== '' ? $ini : 'HQ';
  }

  /**
   * A distinct colour per department. The palette holds enough visually
   * separated hues that neighbouring departments never share one; if a tenant
   * ever exceeds it, extra departments get an evenly-spaced generated hue
   * rather than silently reusing an existing colour.
   */
  private function departmentColours(array $hierarchy): array
  {
    $names = [];
    $walk = function ($nodes) use (&$walk, &$names) {
        foreach ($nodes as $n) {
            $d = trim($n['department'] ?? '');
            if ($d !== '') { $names[$d] = true; }
            $walk($n['children'] ?? []);
        }
    };
    $walk($hierarchy);

    $names = array_keys($names);
    sort($names);

    $palette = [
      '#005a5a', '#c2410c', '#2563eb', '#be123c', '#047857', '#7c3aed',
      '#b45309', '#0f766e', '#9d174d', '#1d4ed8', '#4d7c0f', '#a21caf',
      '#0369a1', '#b91c1c', '#065f46', '#6d28d9', '#92400e', '#155e75',
      '#831843', '#3f6212', '#1e40af', '#7e22ce', '#854d0e', '#0e7490',
    ];

    $map = [];
    foreach ($names as $i => $d) {
        if ($i < count($palette)) {
            $map[$d] = $palette[$i];
        } else {
            // Golden-angle hue spacing keeps generated colours far apart.
            $hue = (int) fmod($i * 137.508, 360);
            $map[$d] = sprintf('hsl(%d, 62%%, 34%%)', $hue);
        }
    }
    return $map;
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
      'phone'           => $user->official_phone ?? 'N/A',
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
          'phone' => $user->official_phone ?? 'N/A',
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
