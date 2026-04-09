<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Nwidart\Modules\Facades\Module;

class RouteMenuValidator extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'routes:validate-menu';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Validate that all menu items have corresponding routes and permissions';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('🔍 Validating Route-Menu Consistency...');
        $this->line('');

        $issues = [];

        // 1. Check menu items vs routes
        $issues = array_merge($issues, $this->validateMenuRoutes());

        // 2. Check permissions on routes
        $issues = array_merge($issues, $this->validateRoutePermissions());

        // 3. Check addon consistency
        $issues = array_merge($issues, $this->validateAddonConsistency());

        // 4. Check role-based access
        $issues = array_merge($issues, $this->validateRoleAccess());

        // Report results
        $this->reportResults($issues);

        return empty($issues) ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * Validate that menu items have corresponding routes
     */
    private function validateMenuRoutes()
    {
        $issues = [];
        $routes = Route::getRoutes()->getRoutes();

        // Expected menu items and their routes
        $menuItems = [
            'dashboard' => ['dashboard', 'tenant.dashboard', 'user.dashboard.index'],
            'employees' => ['employees.index', 'tenant.employees.index'],
            'attendance' => ['attendance.index', 'tenant.attendance.index', 'user.attendance.index'],
            'leaves' => ['leaveRequests.index', 'tenant.leave.index', 'user.leaves.index'],
            'expenses' => ['expenseRequests.index', 'tenant.expenses.index', 'user.expenses.index'],
            'departments' => ['departments.index', 'tenant.departments.index'],
            'designations' => ['designation.index', 'tenant.designation.index'],
            'teams' => ['teams.index', 'tenant.teams.index'],
            'holidays' => ['holidays.index', 'tenant.holidays.index'],
            'settings' => ['settings.index', 'tenant.settings.index'],
            'reports' => ['reports.index', 'tenant.reports.index'],
            'payroll' => ['payroll.index', 'tenant.payroll.index', 'user.payroll.index'],
            'clients' => ['client.index', 'tenant.client.index'],
            'visits' => ['visits.index', 'tenant.visits.index', 'user.visits.index'],
            'devices' => ['devices.index', 'tenant.device.index'],
            'sos' => ['sos.index', 'tenant.sos.index', 'user.sos.index'],
            'permissions' => ['permissions.index', 'tenant.permission.index'],
            'tenants' => ['tenant.index'],
            'accounts' => ['account.index'],
            'roles' => ['roles.index'],
            'addons' => ['addons.index'],
            'orders' => ['orders.index'],
            'coupons' => ['coupons.index'],
            'utilities' => ['utilities.index'],
        ];

        foreach ($menuItems as $menuItem => $expectedRoutes) {
            $hasRoute = false;
            foreach ($expectedRoutes as $routeName) {
                if (Route::has($routeName)) {
                    $hasRoute = true;
                    break;
                }
            }

            if (!$hasRoute) {
                $issues[] = [
                    'type' => 'missing_route',
                    'menu_item' => $menuItem,
                    'expected_routes' => $expectedRoutes,
                    'message' => "Menu item '{$menuItem}' has no matching route"
                ];
            }
        }

        return $issues;
    }

    /**
     * Validate that routes have proper permission middleware
     */
    private function validateRoutePermissions()
    {
        $issues = [];
        $routes = Route::getRoutes()->getRoutes();

        $protectedRoutes = [
            'tenant.*',
            'user.*',
            'admin.*',
            'settings.*',
            'employees.*',
            'attendance.*',
            'leaves.*',
            'expenses.*',
            'payroll.*',
            'departments.*',
            'reports.*',
        ];

        foreach ($routes as $route) {
            $routeName = $route->getName();
            
            if (!$routeName) continue;

            foreach ($protectedRoutes as $pattern) {
                if ($this->matchesPattern($routeName, $pattern)) {
                    $middleware = $route->middleware();
                    $hasAuth = in_array('auth', $middleware);
                    $hasRole = false;

                    foreach ($middleware as $mid) {
                        if (strpos($mid, 'role:') === 0) {
                            $hasRole = true;
                            break;
                        }
                    }

                    if (!$hasAuth) {
                        $issues[] = [
                            'type' => 'missing_auth',
                            'route' => $routeName,
                            'message' => "Route '{$routeName}' missing auth middleware"
                        ];
                    }

                    if (!$hasRole) {
                        $issues[] = [
                            'type' => 'missing_role',
                            'route' => $routeName,
                            'message' => "Route '{$routeName}' missing role middleware"
                        ];
                    }

                    break;
                }
            }
        }

        return $issues;
    }

    /**
     * Validate addon consistency
     */
    private function validateAddonConsistency()
    {
        $issues = [];
        
        $addonModules = [
            'Payroll' => ['payroll', 'payslips', 'salary'],
            'Recruitment' => ['jobs', 'candidates', 'interviews'],
            'Training' => ['courses', 'sessions', 'materials'],
            'Performance' => ['reviews', 'goals', 'feedback'],
            'Calendar' => ['events', 'schedules', 'meetings'],
            'Assets' => ['inventory', 'equipment', 'allocation'],
            'Notes' => ['notes', 'memos', 'reminders'],
            'TaskSystem' => ['tasks', 'assignments', 'projects'],
            'LoanManagement' => ['loans', 'advances', 'repayments'],
            'NoticeBoard' => ['notices', 'announcements', 'bulletins'],
            'DocumentManagement' => ['documents', 'files', 'requests'],
        ];

        foreach ($addonModules as $module => $keywords) {
            $isEnabled = Module::has($module);
            $hasRoutes = $this->hasRoutesForKeywords($keywords);

            if ($isEnabled && !$hasRoutes) {
                $issues[] = [
                    'type' => 'addon_no_routes',
                    'module' => $module,
                    'message' => "Addon '{$module}' is enabled but has no routes"
                ];
            }

            if (!$isEnabled && $hasRoutes) {
                $issues[] = [
                    'type' => 'routes_no_addon',
                    'module' => $module,
                    'message' => "Routes exist for disabled addon '{$module}'"
                ];
            }
        }

        return $issues;
    }

    /**
     * Validate role-based access patterns
     */
    private function validateRoleAccess()
    {
        $issues = [];
        
        // Expected role patterns
        $rolePatterns = [
            'admin' => ['admin.*', 'tenant.*', 'superadmin.*'],
            'hr' => ['hr.*', 'employees.*', 'attendance.*', 'leaves.*', 'expenses.*', 'departments.*'],
            'manager' => ['manager.*', 'team.*', 'reports.*'],
            'employee' => ['user.*', 'attendance.*', 'leaves.*', 'expenses.*'],
            'office_employee' => ['user.*', 'attendance.*', 'leaves.*', 'expenses.*'],
        ];

        $routes = Route::getRoutes()->getRoutes();

        foreach ($routes as $route) {
            $routeName = $route->getName();
            if (!$routeName) continue;

            $middleware = $route->middleware();
            $roleMiddleware = null;

            foreach ($middleware as $mid) {
                if (strpos($mid, 'role:') === 0) {
                    $roleMiddleware = $mid;
                    break;
                }
            }

            if (!$roleMiddleware) continue;

            $roles = explode(',', str_replace('role:', '', $roleMiddleware));
            
            foreach ($roles as $role) {
                $role = trim($role);
                if (!isset($rolePatterns[$role])) {
                    $issues[] = [
                        'type' => 'unknown_role',
                        'route' => $routeName,
                        'role' => $role,
                        'message' => "Route '{$routeName}' uses unknown role '{$role}'"
                    ];
                }
            }
        }

        return $issues;
    }

    /**
     * Check if route name matches pattern
     */
    private function matchesPattern($routeName, $pattern)
    {
        $pattern = str_replace('*', '.*', preg_quote($pattern, '/'));
        return preg_match("/^{$pattern}$/", $routeName);
    }

    /**
     * Check if routes exist for keywords
     */
    private function hasRoutesForKeywords($keywords)
    {
        $routes = Route::getRoutes()->getRoutes();
        
        foreach ($routes as $route) {
            $routeName = $route->getName();
            if (!$routeName) continue;

            foreach ($keywords as $keyword) {
                if (strpos($routeName, $keyword) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Report validation results
     */
    private function reportResults($issues)
    {
        if (empty($issues)) {
            $this->info('✅ All validations passed! Route-Menu consistency is perfect.');
            return;
        }

        $this->error('❌ Found ' . count($issues) . ' issues:');
        $this->line('');

        // Group by type
        $grouped = [];
        foreach ($issues as $issue) {
            $grouped[$issue['type']][] = $issue;
        }

        foreach ($grouped as $type => $typeIssues) {
            $this->warn("🔸 " . ucfirst(str_replace('_', ' ', $type)) . " (" . count($typeIssues) . "):");
            
            foreach ($typeIssues as $issue) {
                $this->line("   • " . $issue['message']);
                if (isset($issue['expected_routes'])) {
                    $this->line("     Expected: " . implode(', ', $issue['expected_routes']));
                }
            }
            $this->line('');
        }

        $this->info('💡 Run this command in CI/CD pipeline to catch issues early.');
    }
}
