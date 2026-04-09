<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Nwidart\Modules\Facades\Module;

class MenuPermissionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Get current user
        $user = Auth::user();
        
        if (!$user) {
            return $next($request);
        }

        // Build permission map for menu visibility
        $menuPermissions = $this->buildMenuPermissionMap($user);
        
        // Share with all views
        view()->share('menuPermissions', $menuPermissions);
        
        return $next($request);
    }

    /**
     * Build menu permission map based on user roles and addon status
     */
    private function buildMenuPermissionMap($user)
    {
        $permissions = [];
        
        // Core permissions based on roles
        if ($user->hasRole('admin')) {
            $permissions = $this->getAdminPermissions();
        } elseif ($user->hasRole('hr')) {
            $permissions = $this->getHRPermissions();
        } elseif ($user->hasRole('manager')) {
            $permissions = $this->getManagerPermissions();
        } elseif ($user->hasRole('employee') || $user->hasRole('office_employee')) {
            $permissions = $this->getEmployeePermissions();
        }

        // Apply addon-based permissions
        $permissions = $this->applyAddonPermissions($permissions);
        
        return $permissions;
    }

    /**
     * Get admin permissions
     */
    private function getAdminPermissions()
    {
        return [
            'dashboard' => true,
            'tenants' => true,
            'accounts' => true,
            'roles' => true,
            'permissions' => true,
            'addons' => true,
            'orders' => true,
            'coupons' => true,
            'utilities' => true,
            'audit_logs' => true,
            'notifications' => true,
        ];
    }

    /**
     * Get HR permissions
     */
    private function getHRPermissions()
    {
        return [
            'dashboard' => true,
            'employees' => true,
            'attendance' => true,
            'leaves' => true,
            'expenses' => true,
            'departments' => true,
            'designations' => true,
            'teams' => true,
            'holidays' => true,
            'leave_types' => true,
            'expense_types' => true,
            'document_types' => true,
            'documentmanagement' => true,
            'clients' => true,
            'visits' => true,
            'devices' => true,
            'sos' => true,
            'settings' => true,
            'reports' => true,
            'permissions' => true,
            'organisation_hierarchy' => true,
        ];
    }

    /**
     * Get Manager permissions
     */
    private function getManagerPermissions()
    {
        return [
            'dashboard' => true,
            'employees' => true,
            'attendance' => true,
            'leaves' => true,
            'expenses' => true,
            'departments' => true,
            'teams' => true,
            'holidays' => true,
            'clients' => true,
            'visits' => true,
            'devices' => true,
            'sos' => true,
            'reports' => true,
        ];
    }

    /**
     * Get Employee permissions
     */
    private function getEmployeePermissions()
    {
        return [
            'dashboard' => true,
            'attendance' => true,
            'leaves' => true,
            'expenses' => true,
            'payroll' => true,
            'sos' => true,
            'visits' => true,
            'profile' => true,
        ];
    }

    /**
     * Apply addon-based permissions
     */
    private function applyAddonPermissions($permissions)
    {
        // Check addon status and enable/disable menu items
        $addonModules = [
            'payroll' => Module::has('Payroll'),
            'recruitment' => Module::has('Recruitment'),
            'training' => Module::has('Training'),
            'performance' => Module::has('Performance'),
            'calendar' => Module::has('Calendar'),
            'assets' => Module::has('Assets'),
            'notes' => Module::has('Notes'),
            'tasks' => Module::has('TaskSystem'),
            'loans' => Module::has('LoanManagement'),
            'notices' => Module::has('NoticeBoard'),
            'documents' => Module::has('DocumentManagement'),
        ];

        foreach ($addonModules as $module => $enabled) {
            if (!$enabled) {
                unset($permissions[$module]);
            }
        }

        return $permissions;
    }
}
