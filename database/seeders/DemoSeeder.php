<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\AttendanceLog;
use App\Models\Client;
use App\Models\Department;
use App\Models\Designation;
use App\Models\DocumentType;
use App\Models\ExpenseType;
use App\Models\GeofenceGroup;
use App\Models\GeofenceLocation;
use App\Models\Holiday;
use App\Models\IpAddress;
use App\Models\IpAddressGroup;
use App\Models\LeaveType;
use App\Models\Notice;
use App\Models\PayrollAdjustment;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\QrCodeModel;
use App\Models\QrGroup;
use App\Models\Settings;
use App\Models\Shift;
use App\Models\Site;
use App\Models\Team;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class DemoSeeder extends Seeder
{
  /**
   * Run the database seeds.
   */
  public function run(): void
  {
    $this->command->info('Seeding Demo data...');

      Role::create(['name' => 'admin', 'guard_name' => 'web', 'is_web_access_enabled' => true, 'is_mobile_app_access_enabled' => true]);
      Role::create(['name' => 'hr', 'guard_name' => 'web', 'is_mobile_app_access_enabled' => true, 'is_web_access_enabled' => true, 'is_multiple_check_in_enabled' => true]);
      Role::create(['name' => 'employee', 'guard_name' => 'web', 'is_mobile_app_access_enabled' => true, 'is_web_access_enabled' => false, 'is_location_activity_tracking_enabled' => true, 'is_multiple_check_in_enabled' => true]);
      Role::create(['name' => 'office_employee', 'guard_name' => 'web', 'is_mobile_app_access_enabled' => true, 'is_multiple_check_in_enabled' => true]);
      Role::create(['name' => 'manager', 'guard_name' => 'web', 'is_mobile_app_access_enabled' => true, 'is_multiple_check_in_enabled' => true]);

      $this->seedTeamData();

      $this->seedShiftData();

      $this->seedClientData();

      $this->seedLeaveTypesData();

      $this->seedExpenseTypesData();

      $this->seedDepartmentDesignationData();

      $shift = Shift::where('is_default', true)->first();

      $team = Team::first();

      $adminDesignation = Designation::where('name', 'Admin Manager')->first();
      $hrDesignation = Designation::where('name', 'HR Manager')->first();
      $salesDesignation = Designation::where('name', 'Sales Representative')->first();
      $salesManagerDesignation = Designation::where('name', 'Sales Manager')->first();

      PayrollAdjustment::create([
        'name' => 'Tax',
        'code' => 'TAX',
        'type' => 'deduction',
        'applicability' => 'global',
        'percentage' => 10,
      ]);

      $this->command->info('Tax Adjustment seeded!');

      PayrollAdjustment::create([
        'name' => 'Bonus',
        'code' => 'BONUS',
        'type' => 'benefit',
        'applicability' => 'global',
        'amount' => 100,
      ]);


      $admin = User::factory()->create([
        'first_name' => 'Admin',
        'last_name' => 'User',
        'email' => 'democustomer@rustx.com',
        'phone' => '1234567890',
        'phone_verified_at' => now(),
        'password' => bcrypt('123456'),
        'code' => 'DEMO-001',
        'email_verified_at' => now(),
        'remember_token' => Str::random(10),
        'shift_id' => $shift->id,
        'team_id' => $team->id,
        'designation_id' => $adminDesignation->id,

      ]);

      $admin->assignRole('admin');

      $this->command->info('Admin seeded!');

      $hrUser = User::factory()->create([
        'first_name' => 'HR',
        'last_name' => 'User',
        'email' => 'hr@rustx.com',
        'phone' => '0987654321',
        'phone_verified_at' => now(),
        'password' => bcrypt('123456'),
        'code' => 'DEMO002',
        'email_verified_at' => now(),
        'remember_token' => Str::random(10),
        'shift_id' => $shift->id,
        'team_id' => $team->id,
        'reporting_to_id' => $admin->id,
        'designation_id' => $hrDesignation->id,
        'base_salary' => 2500,

      ]);

      $hrUser->assignRole('hr');

      $this->command->info('Hr Seeded!');

      $managerUser = User::factory()->create([
        'first_name' => 'Manager',
        'last_name' => 'User',
        'email' => 'manager@rustx.com',
        'phone' => '0988654321',
        'phone_verified_at' => now(),
        'password' => bcrypt('123456'),
        'code' => 'DEMO003',
        'email_verified_at' => now(),
        'remember_token' => Str::random(10),
        'shift_id' => $shift->id,
        'team_id' => $team->id,
        'reporting_to_id' => $hrUser->id,
        'designation_id' => $salesManagerDesignation->id,
        'base_salary' => 2000,

      ]);

      $managerUser->assignRole('manager');

      $this->command->info('Manager Seeded!');


      $user = User::factory()->create([
        'first_name' => 'Demo',
        'last_name' => 'Employee',
        'email' => 'employee@rustx.com',
        'phone' => '123899890',
        'phone_verified_at' => now(),
        'password' => bcrypt('123456'),
        'code' => 'DEMO-004',
        'email_verified_at' => now(),
        'remember_token' => Str::random(10),
        'base_salary' => 1900,
        'shift_id' => $shift->id,
        'team_id' => $team->id,
        'reporting_to_id' => $managerUser->id,
        'designation_id' => $salesDesignation->id,

      ]);

      $user->assignRole('employee');


      $user1 = User::factory()->create([
        'first_name' => 'Robin',
        'last_name' => 'Son',
        'email' => 'robinson@rustx.com',
        'phone' => '126669890',
        'phone_verified_at' => now(),
        'password' => bcrypt('123456'),
        'code' => 'DEMO-005',
        'email_verified_at' => now(),
        'remember_token' => Str::random(10),
        'base_salary' => 1500,
        'shift_id' => $shift->id,
        'team_id' => $team->id,
        'reporting_to_id' => $managerUser->id,
        'designation_id' => $salesDesignation->id,

      ]);

      $user1->assignRole('employee');

      $user2 = User::factory()->create([
        'first_name' => 'Andrew',
        'last_name' => 'Russell',
        'email' => 'andrewrussell@rustx.com',
        'phone' => '1244469890',
        'phone_verified_at' => now(),
        'password' => bcrypt('123456'),
        'code' => 'DEMO-006',
        'email_verified_at' => now(),
        'remember_token' => Str::random(10),
        'base_salary' => 1100,
        'shift_id' => $shift->id,
        'team_id' => $team->id,
        'reporting_to_id' => $managerUser->id,
        'designation_id' => $salesDesignation->id,

      ]);

      $user2->assignRole('employee');


      $user3 = User::factory()->create([
        'first_name' => 'Hari',
        'last_name' => 'haran',
        'email' => 'demohari@rustx.com',
        'phone' => '8994469890',
        'phone_verified_at' => now(),
        'password' => bcrypt('123456'),
        'code' => 'DEMO-007',
        'email_verified_at' => now(),
        'remember_token' => Str::random(10),
        'base_salary' => 900,
        'shift_id' => $shift->id,
        'team_id' => $team->id,
        'reporting_to_id' => $managerUser->id,
        'designation_id' => $salesDesignation->id,

      ]);

      $user3->assignRole('employee');


      $this->command->info('User seeded!');


      $this->seedSettings();

      $this->seedAddonData();
      //$this->seedAttendancesAndLogs();
      //$this->command->info('Seeded Attendance Logs!');

    $this->command->info('Tenant data seeded!');
  }

  private function seedTeamData()
  {

    $team = new Team();
    $team->name = 'Default Team';
    $team->code = 'TM-001';
    $team->status = 'active';
    $team->is_chat_enabled = true;

    $team->save();

    Team::create(['name' => 'Sales Team 1', 'code' => 'TM-002', 'status' => 'active', 'is_chat_enabled' => true]);
    Team::create(['name' => 'Demo Team', 'code' => 'TM-003', 'status' => 'active', 'is_chat_enabled' => true]);
    Team::create(['name' => 'Team 3', 'code' => 'TM-004', 'status' => 'active', 'is_chat_enabled' => true]);
  }

  private function seedShiftData()
  {
    $shift = new Shift();
    $shift->name = 'Default Shift';
    $shift->code = 'SH-001';
    $shift->status = 'active';
    $shift->start_date = now();
    $shift->start_time = '09:00:00';
    $shift->end_time = '18:00:00';
    $shift->is_default = true;
    $shift->sunday = false;
    $shift->monday = true;
    $shift->tuesday = true;
    $shift->wednesday = true;
    $shift->thursday = true;
    $shift->friday = true;
    $shift->saturday = false;

    $shift->save();


    Shift::create([
      'name' => 'Evening Shift',
      'code' => 'SH-002',
      'status' => 'active',
      'start_date' => now(),
      'start_time' => '14:00:00',
      'end_time' => '22:00:00',
      'is_default' => false,
      'sunday' => false,
      'monday' => true,
      'tuesday' => true,
      'wednesday' => true,
      'thursday' => true,
      'friday' => true,
      'saturday' => false
    ]);

    Shift::create([
      'name' => 'Night Shift',
      'code' => 'SH-003',
      'status' => 'active',
      'start_date' => now(),
      'start_time' => '22:00:00',
      'end_time' => '06:00:00',
      'is_default' => false,
      'sunday' => false,
      'monday' => true,
      'tuesday' => true,
      'wednesday' => true,
      'thursday' => true,
      'friday' => true,
      'saturday' => false
    ]);

  }

  private function seedClientData()
  {
    $client = new Client();
    $client->name = 'Test Client';
    $client->address = 'Default Address';
    $client->email = 'defaultclient@demo.com';
    $client->latitude = 13.067439;
    $client->longitude = 80.237617;
    $client->phone = '1234567890';
    $client->contact_person_name = 'Default Contact Person';
    $client->radius = 100;
    $client->city = 'Chennai';
    $client->state = 'Tamil Nadu';
    $client->save();


    Client::create([
      'name' => 'Client 1',
      'address' => 'Address 1',
      'email' => 'client1@demo.com',
      'latitude' => 13.067439,
      'longitude' => 80.237617,
      'phone' => '1234567890',
      'contact_person_name' => 'Contact Person 1',
      'radius' => 100,
      'city' => 'Chennai',
      'state' => 'Tamil Nadu'
    ]);

    Client::create([
      'name' => 'Client 2',
      'address' => 'Address 2',
      'email' => 'client2@demo.com',
      'latitude' => 13.067439,
      'longitude' => 80.237617,
      'phone' => '1234567890',
      'contact_person_name' => 'Contact Person 2',
      'radius' => 100,
      'city' => 'Chennai',
      'state' => 'Tamil Nadu'
    ]);


    Client::create([
      'name' => 'Client 3',
      'address' => 'Address 3',
      'email' => 'client3@demo.com',
      'latitude' => 13.067439,
      'longitude' => 80.237617,
      'phone' => '1234567890',
      'contact_person_name' => 'Contact Person 3',
      'radius' => 100,
      'city' => 'Chennai',
      'state' => 'Tamil Nadu'
    ]);
  }

  private function seedLeaveTypesData(): void
  {
    $leaves = [
      ['name' => 'Casual Leave', 'code' => 'CL', 'description' => 'Casual Leave', 'is_proof_required' => false],
      ['name' => 'Sick Leave', 'code' => 'SL', 'description' => 'Sick Leave', 'is_proof_required' => false],
      ['name' => 'Paid Leave', 'code' => 'PL', 'description' => 'Paid Leave', 'is_proof_required' => true],
      ['name' => 'Unpaid Leave', 'code' => 'UL', 'description' => 'Unpaid Leave', 'is_proof_required' => true],
    ];

    foreach ($leaves as $leave) {
      $leaveType = new LeaveType();
      $leaveType->name = $leave['name'];
      $leaveType->code = $leave['code'];
      $leaveType->notes = $leave['description'];
      $leaveType->is_proof_required = $leave['is_proof_required'];
      $leaveType->save();
    }
  }

  private function seedExpenseTypesData(): void
  {
    $expenseTypes = [
      ['name' => 'Travel', 'code' => 'TRAVEL', 'description' => 'Travel Expense', 'is_proof_required' => false],
      ['name' => 'Food', 'code' => 'FOOD', 'description' => 'Food Expense', 'is_proof_required' => false],
      ['name' => 'Accommodation', 'code' => 'ACCOMMODATION', 'description' => 'Accommodation Expense', 'is_proof_required' => false],
      ['name' => 'Miscellaneous', 'code' => 'MISC', 'description' => 'Miscellaneous Expense', 'is_proof_required' => false],
    ];

    foreach ($expenseTypes as $expenseType) {
      $et = new ExpenseType();
      $et->name = $expenseType['name'];
      $et->code = $expenseType['code'];
      $et->notes = $expenseType['description'];
      $et->is_proof_required = $expenseType['is_proof_required'];
      $et->save();
    }
  }

  private function seedDepartmentDesignationData(): void
  {
    $department = Department::create([
      'name' => 'Default Department',
      'code' => 'DEPT-001',
      'notes' => 'Default Department',
    ]);

    Designation::create([
      'name' => 'Default Designation',
      'code' => 'DES-001',
      'department_id' => $department->id,
      'notes' => 'Default Designation',
    ]);

    $salesDepartment = Department::create([
      'name' => 'Sales Department',
      'code' => 'DEPT-002',
      'notes' => 'Sales Department',
    ]);

    Designation::create([
      'name' => 'Sales Manager',
      'code' => 'DES-002',
      'department_id' => $salesDepartment->id,
      'notes' => 'Sales Manager',
    ]);

    Designation::create([
      'name' => 'Sales Executive',
      'code' => 'DES-003',
      'department_id' => $salesDepartment->id,
      'notes' => 'Sales Executive',
    ]);

    Designation::create([
      'name' => 'Sales Associate',
      'code' => 'DES-004',
      'department_id' => $salesDepartment->id,
      'notes' => 'Sales Associate',
    ]);

    Designation::create([
      'name' => 'Sales Representative',
      'code' => 'DES-005',
      'department_id' => $salesDepartment->id,
      'notes' => 'Sales Representative',
    ]);

    $hrDepartment = Department::create([
      'name' => 'HR Department',
      'code' => 'DEPT-003',
      'notes' => 'HR Department',
    ]);

    Designation::create([
      'name' => 'HR Manager',
      'code' => 'DES-006',
      'department_id' => $hrDepartment->id,
      'notes' => 'HR Manager',
      'is_leave_approver' => true,
      'is_expense_approver' => true,
      'is_loan_approver' => true,
      'is_document_approver' => true,
    ]);

    Designation::create([
      'name' => 'HR Executive',
      'code' => 'DES-007',
      'department_id' => $hrDepartment->id,
      'notes' => 'HR Executive',
    ]);

    Designation::create([
      'name' => 'HR Associate',
      'code' => 'DES-008',
      'department_id' => $hrDepartment->id,
      'notes' => 'HR Associate',
    ]);

    $itDepartment = Department::create([
      'name' => 'IT Department',
      'code' => 'DEPT-004',
      'notes' => 'IT Department',
    ]);

    Designation::create([
      'name' => 'IT Manager',
      'code' => 'DES-009',
      'department_id' => $itDepartment->id,
      'notes' => 'IT Manager',
    ]);

    Designation::create([
      'name' => 'IT Executive',
      'code' => 'DES-010',
      'department_id' => $itDepartment->id,
      'notes' => 'IT Executive',
    ]);

    Designation::create([
      'name' => 'IT Associate',
      'code' => 'DES-011',
      'department_id' => $itDepartment->id,
      'notes' => 'IT Associate',
    ]);

    $financeDepartment = Department::create([
      'name' => 'Finance Department',
      'code' => 'DEPT-005',
      'notes' => 'Finance Department',
    ]);

    Designation::create([
      'name' => 'Finance Manager',
      'code' => 'DES-012',
      'department_id' => $financeDepartment->id,
      'notes' => 'Finance Manager',
    ]);

    Designation::create([
      'name' => 'Finance Executive',
      'code' => 'DES-013',
      'department_id' => $financeDepartment->id,
      'notes' => 'Finance Executive',
    ]);

    Designation::create([
      'name' => 'Finance Associate',
      'code' => 'DES-014',
      'department_id' => $financeDepartment->id,
      'notes' => 'Finance Associate',
    ]);

    $marketingDepartment = Department::create([
      'name' => 'Marketing Department',
      'code' => 'DEPT-006',
      'notes' => 'Marketing Department',
    ]);

    Designation::create([
      'name' => 'Marketing Manager',
      'code' => 'DES-015',
      'department_id' => $marketingDepartment->id,
      'notes' => 'Marketing Manager',
    ]);

    Designation::create([
      'name' => 'Marketing Executive',
      'code' => 'DES-016',
      'department_id' => $marketingDepartment->id,
      'notes' => 'Marketing Executive',
    ]);

    Designation::create([
      'name' => 'Marketing Associate',
      'code' => 'DES-017',
      'department_id' => $marketingDepartment->id,
      'notes' => 'Marketing Associate',
    ]);

    $operationsDepartment = Department::create([
      'name' => 'Operations Department',
      'code' => 'DEPT-007',
      'notes' => 'Operations Department',
    ]);

    Designation::create([
      'name' => 'Operations Manager',
      'code' => 'DES-018',
      'department_id' => $operationsDepartment->id,
      'notes' => 'Operations Manager',
    ]);

    Designation::create([
      'name' => 'Operations Executive',
      'code' => 'DES-019',
      'department_id' => $operationsDepartment->id,
      'notes' => 'Operations Executive',
    ]);

    Designation::create([
      'name' => 'Operations Associate',
      'code' => 'DES-020',
      'department_id' => $operationsDepartment->id,
      'notes' => 'Operations Associate',
    ]);

    $adminDepartment = Department::create([
      'name' => 'Admin Department',
      'code' => 'DEPT-008',
      'notes' => 'Admin Department',
    ]);

    Designation::create([
      'name' => 'Admin Manager',
      'code' => 'DES-021',
      'department_id' => $adminDepartment->id,
      'notes' => 'Admin Manager',
    ]);

    Designation::create([
      'name' => 'Admin Executive',
      'code' => 'DES-022',
      'department_id' => $adminDepartment->id,
      'notes' => 'Admin Executive',
    ]);

    Designation::create([
      'name' => 'Admin Associate',
      'code' => 'DES-023',
      'department_id' => $adminDepartment->id,
      'notes' => 'Admin Associate',
    ]);

  }

  private function seedSettings(): void
  {
    $this->command->info('Seed Settings data...');

    $settings = new Settings();
    $settings->available_modules = ["LeaveManagement", "ExpenseManagement", "ClientVisit", "ChatSystem", "BreakSystem", "DocumentManagement", "DynamicForms", "GeofenceSystem", "IpAddressAttendance", "LoanManagement", "ManagerApp", "NoticeBoard", "OfflineTracking", "PaymentCollection", "ProductOrder", "QRAttendance", "SiteAttendance", "TaskSystem", "UidLogin", "AiChat", "DataImportExport", "DynamicQrAttendance", "Payroll", "DigitalIdCard", "SalesTarget", 'SoS'];
    $settings->employees_limit = 1000;
    $settings->website = 'https://hitechhrx.com';
    $settings->support_email = 'info@hitechgroup.in';
    $settings->support_phone = '+91 9814215000';
    $settings->support_whatsapp = '+91 9814215000';

    $settings->company_name = 'HITECH HRX';
    $settings->company_address = 'plot 18 Sector 6 IMT Manesar Gurugram Haryana';
    $settings->company_phone = '+91-9814215000';
    $settings->company_email = 'info@hitechgroup.in';
    $settings->company_website = 'https://hitechhrx.com';
    $settings->company_country = 'India';
    $settings->company_state = 'Haryana';
    $settings->company_city = 'Gurgaon';
    $settings->company_zipcode = '122002';
    $settings->company_tax_id = 'GSTIN1234567890';
    $settings->company_reg_no = 'REG1234567890';

    $settings->save();

    $this->command->info('Settings seeded!');
  }

  private function seedAddonData()
  {
    $this->command->info('Seed Addon data...');
    $this->seedGeofenceData();
    $this->seedIpAddressData();
    $this->seedQrGroupData();
    $this->seedDocumentTypesData();
    $this->seedProductData();
    $this->seedHolidaysData();
    $this->seedSiteData();
    $this->seedNoticeBoardData();
    $this->command->info('Addon data seeded!');
  }


  private function seedGeofenceData()
  {
    $group1 = GeofenceGroup::create([
      'name' => 'Default Geofence Group',
      'code' => 'GFG-001',
      'description' => 'Default Geofence Group',
    ]);

    $group2 = GeofenceGroup::create([
      'name' => 'Warehouse',
      'code' => 'GFG-002',
      'description' => 'Geofence Group 2',
    ]);


    GeofenceLocation::create([
      'name' => 'Default Geofence Location',
      'latitude' => 13.067439,
      'longitude' => 80.237617,
      'radius' => 100,
      'geofence_group_id' => $group1->id,
    ]);

    GeofenceLocation::create([
      'name' => 'Warehouse A Location',
      'latitude' => 13.067439,
      'longitude' => 80.237617,
      'radius' => 100,
      'geofence_group_id' => $group2->id,
    ]);

    GeofenceLocation::create([
      'name' => 'Warehouse B Location',
      'latitude' => 13.067439,
      'longitude' => 80.237617,
      'radius' => 100,
      'geofence_group_id' => $group2->id,
    ]);

    GeofenceLocation::create([
      'name' => 'Head Office',
      'latitude' => 13.067439,
      'longitude' => 80.237617,
      'radius' => 100,
      'geofence_group_id' => $group1->id,
    ]);

  }

  private function seedIpAddressData()
  {
    $group1 = IpAddressGroup::create([
      'name' => 'Office 1',
      'code' => 'IP-001',
      'description' => 'Default IP Address Group',
    ]);

    $group2 = IpAddressGroup::create([
      'name' => 'Head Office',
      'code' => 'IP-002',
      'description' => 'IP Address Group 2',
    ]);

    IpAddress::create([
      'name' => 'Main Router',
      'ip_address' => '192.168.1.110',
      'ip_address_group_id' => $group1->id,
    ]);

    IpAddress::create([
      'name' => 'Head Office Router',
      'ip_address' => '192.166.222.56',
      'ip_address_group_id' => $group2->id,
    ]);

    IpAddress::create([
      'name' => 'Warehouse Router',
      'ip_address' => '178.267.88.4',
      'ip_address_group_id' => $group2->id,
    ]);
  }

  private function seedQrGroupData()
  {
    $qrGroup1 = QrGroup::create([
      'name' => 'Default QR Group',
      'code' => 'QR-001',
      'description' => 'Default QR Group',
    ]);

    $qrGroup2 = QrGroup::create([
      'name' => 'Warehouse',
      'code' => 'QR-002',
      'description' => 'QR Group 2',
    ]);

    QrCodeModel::create([
      'qr_group_id' => $qrGroup1->id,
      'name' => 'Default QR Code',
      'code' => 'QRC9999828403',
      'description' => 'Default QR Code',
    ]);

    QrCodeModel::create([
      'qr_group_id' => $qrGroup2->id,
      'name' => 'Warehouse A QR Code',
      'code' => 'QRC9999828404',
      'description' => 'Warehouse A QR Code',
    ]);

    QrCodeModel::create([
      'qr_group_id' => $qrGroup2->id,
      'name' => 'Warehouse B QR Code',
      'code' => 'QRC9999828405',
      'description' => 'Warehouse B QR Code',
    ]);

  }

  private function seedDocumentTypesData()
  {
    DocumentType::create([
      'name' => 'NOC',
      'code' => 'NOC',
      'notes' => 'No Objection Certificate',
      'is_required' => true,
    ]);

    DocumentType::create([
      'name' => 'Salary Slip',
      'code' => 'SALARY_SLIP',
      'notes' => 'Salary Slip',
      'is_required' => true,
    ]);

  }

  private function seedProductData()
  {
    $samsungProducts = ProductCategory::create([
      'name' => 'Samsung',
      'code' => 'SP-001',
      'description' => 'Samsung Products'
    ]);

    $tablet = ProductCategory::create([
      'name' => 'Samsung Tablet',
      'code' => 'TB-001',
      'description' => 'Tablet',
      'parent_id' => $samsungProducts->id
    ]);

    $phone = ProductCategory::create([
      'name' => 'Samsung Phone',
      'code' => 'PH-001',
      'description' => 'Phone',
      'parent_id' => $samsungProducts->id
    ]);

    $homeAppliances = ProductCategory::create([
      'name' => 'Samsung Home Appliances',
      'code' => 'HA-001',
      'description' => 'Home Appliances',
      'parent_id' => $samsungProducts->id
    ]);

    $watch = ProductCategory::create([
      'name' => 'Samsung Watch',
      'code' => 'SW-001',
      'description' => 'Watch',
      'parent_id' => $samsungProducts->id
    ]);


    $iphoneProducts = ProductCategory::create([
      'name' => 'Apple',
      'code' => 'IPHN-001',
      'description' => 'Apple Products'
    ]);

    $iphone = ProductCategory::create([
      'name' => 'Apple iPhone',
      'code' => 'IP-001',
      'description' => 'iPhone',
      'parent_id' => $iphoneProducts->id
    ]);

    $ipad = ProductCategory::create([
      'name' => 'Apple iPad',
      'code' => 'IP-002',
      'description' => 'iPad',
      'parent_id' => $iphoneProducts->id
    ]);

    $macbook = ProductCategory::create([
      'name' => 'Apple MacBook',
      'code' => 'IP-003',
      'description' => 'MacBook',
      'parent_id' => $iphoneProducts->id
    ]);

    $appleWatch = ProductCategory::create([
      'name' => 'Apple Watch',
      'code' => 'IP-004',
      'description' => 'Watch',
      'parent_id' => $iphoneProducts->id
    ]);

    Product::create([
      'name' => 'Samsung Galaxy Tab S7',
      'product_code' => 'SGTS7',
      'description' => 'Samsung Galaxy Tab S7',
      'price' => 1000,
      'base_price' => 1000,
      'category_id' => $tablet->id
    ]);

    Product::create([
      'name' => 'Samsung Galaxy Tab S6',
      'product_code' => 'SGTS6',
      'description' => 'Samsung Galaxy Tab S6',
      'price' => 800,
      'base_price' => 800,
      'category_id' => $tablet->id
    ]);

    Product::create([
      'name' => 'Samsung Galaxy Tab S5',
      'product_code' => 'SGTS5',
      'description' => 'Samsung Galaxy Tab S5',
      'price' => 600,
      'base_price' => 600,
      'category_id' => $tablet->id
    ]);

    Product::create([
      'name' => 'Samsung Galaxy S24 Ultra',
      'product_code' => 'SGS24U',
      'description' => 'Samsung Galaxy S24 Ultra',
      'price' => 1500,
      'base_price' => 1500,
      'category_id' => $phone->id
    ]);

    Product::create([
      'name' => 'Samsung Galaxy S23 Ultra',
      'product_code' => 'SGS23U',
      'description' => 'Samsung Galaxy S23 Ultra',
      'price' => 1300,
      'base_price' => 1300,
      'category_id' => $phone->id
    ]);

    Product::create([
      'name' => 'Samsung Galaxy S21 Ultra',
      'product_code' => 'SGS21U',
      'description' => 'Samsung Galaxy S21 Ultra',
      'price' => 1100,
      'base_price' => 1100,
      'category_id' => $phone->id
    ]);

    Product::create([
      'name' => 'Samsung Galaxy S20 Ultra',
      'product_code' => 'SGS20U',
      'description' => 'Samsung Galaxy S20 Ultra',
      'price' => 900,
      'base_price' => 900,
      'category_id' => $phone->id
    ]);

    Product::create([
      'name' => 'Samsung Double Door Refrigerator',
      'product_code' => 'SDDR',
      'description' => 'Samsung Double Door Refrigerator',
      'price' => 1000,
      'base_price' => 1000,
      'category_id' => $homeAppliances->id
    ]);

    Product::create([
      'name' => 'Samsung Washing Machine',
      'product_code' => 'SWM',
      'description' => 'Samsung Washing Machine',
      'price' => 800,
      'base_price' => 800,
      'category_id' => $homeAppliances->id
    ]);

    Product::create([
      'name' => 'Samsung Microwave Oven',
      'product_code' => 'SMO',
      'description' => 'Samsung Microwave Oven',
      'price' => 600,
      'base_price' => 600,
      'category_id' => $homeAppliances->id
    ]);


    Product::create([
      'name' => 'Samsung Galaxy Watch 4',
      'product_code' => 'SGW4',
      'description' => 'Samsung Galaxy Watch 4',
      'price' => 500,
      'base_price' => 500,
      'category_id' => $watch->id
    ]);

    Product::create([
      'name' => 'Samsung Galaxy Watch 3',
      'product_code' => 'SGW3',
      'description' => 'Samsung Galaxy Watch 3',
      'price' => 400,
      'base_price' => 400,
      'category_id' => $watch->id
    ]);

    //Iphone
    Product::create([
      'name' => 'iPhone 16 Pro Max',
      'product_code' => 'IP16PM',
      'description' => 'iPhone 16 Pro Max',
      'price' => 1500,
      'base_price' => 1500,
      'category_id' => $iphone->id
    ]);

    Product::create([
      'name' => 'iPhone 15 Pro Max',
      'product_code' => 'IP15PM',
      'description' => 'iPhone 15 Pro Max',
      'price' => 1300,
      'base_price' => 1300,
      'category_id' => $iphone->id
    ]);

    Product::create([
      'name' => 'iPhone 14 Pro Max',
      'product_code' => 'IP14PM',
      'description' => 'iPhone 14 Pro Max',
      'price' => 1100,
      'base_price' => 1100,
      'category_id' => $iphone->id
    ]);

    Product::create([
      'name' => 'iPhone 13 Pro Max',
      'product_code' => 'IP13PM',
      'description' => 'iPhone 13 Pro Max',
      'price' => 900,
      'base_price' => 900,
      'category_id' => $iphone->id
    ]);

    Product::create([
      'name' => 'Apple iPad Pro 2024',
      'product_code' => 'IPADP24',
      'description' => 'Apple iPad Pro 2024',
      'price' => 1000,
      'base_price' => 1000,
      'category_id' => $ipad->id
    ]);

    Product::create([
      'name' => 'Apple iPad Pro 2023',
      'product_code' => 'IPADP23',
      'description' => 'Apple iPad Pro 2023',
      'price' => 800,
      'base_price' => 800,
      'category_id' => $ipad->id
    ]);

    Product::create([
      'name' => 'Apple iPad Pro 2022',
      'product_code' => 'IPADP22',
      'description' => 'Apple iPad Pro 2022',
      'price' => 600,
      'base_price' => 600,
      'category_id' => $ipad->id
    ]);

    Product::create([
      'name' => 'Apple MacBook Pro 2024',
      'product_code' => 'MBP24',
      'description' => 'Apple MacBook Pro 2024',
      'price' => 1500,
      'base_price' => 1500,
      'category_id' => $macbook->id
    ]);

    Product::create([
      'name' => 'Apple MacBook Pro 2023',
      'product_code' => 'MBP23',
      'description' => 'Apple MacBook Pro 2023',
      'price' => 1300,
      'base_price' => 1300,
      'category_id' => $macbook->id
    ]);

    Product::create([
      'name' => 'Apple MacBook Pro 2022',
      'product_code' => 'MBP22',
      'description' => 'Apple MacBook Pro 2022',
      'price' => 1100,
      'base_price' => 1100,
      'category_id' => $macbook->id
    ]);

    Product::create([
      'name' => 'Apple Watch Series 8',
      'product_code' => 'AWS8',
      'description' => 'Apple Watch Series 8',
      'price' => 500,
      'base_price' => 500,
      'category_id' => $appleWatch->id
    ]);

  }

  private function seedHolidaysData()
  {
    Holiday::create([
      'name' => 'New Year',
      'date' => Carbon::parse('2025-01-01'),
      'code' => 'NY',
      'notes' => 'New Year Holiday',
    ]);

    Holiday::create([
      'name' => 'Pongal',
      'date' => Carbon::parse('2025-01-14'),
      'code' => 'PONGAL',
      'notes' => 'Pongal Holiday',
    ]);

    Holiday::create([
      'name' => 'Republic Day',
      'date' => Carbon::parse('2025-01-26'),
      'code' => 'RD',
      'notes' => 'Republic Day Holiday',
    ]);

    Holiday::create([
      'name' => 'Good Friday',
      'date' => Carbon::parse('2025-04-18'),
      'code' => 'GF',
      'notes' => 'Good Friday Holiday',
    ]);

    Holiday::create([
      'name' => 'May Day',
      'date' => Carbon::parse('2025-05-01'),
      'code' => 'MD',
      'notes' => 'May Day Holiday',
    ]);

    Holiday::create([
      'name' => 'Independence Day',
      'date' => Carbon::parse('2025-08-15'),
      'code' => 'ID',
      'notes' => 'Independence Day Holiday',
    ]);
  }

  private function seedSiteData()
  {
    $client = Client::first();

    Site::create([
      'name' => 'Site 1',
      'radius' => 100,
      'description' => 'Site 1',
      'address' => 'Site 1 Address',
      'client_id' => $client->id,
      'latitude' => 13.067439,
      'longitude' => 80.237617,
    ]);

    Site::create([
      'name' => 'Site 2',
      'radius' => 100,
      'description' => 'Site 2',
      'address' => 'Site 2 Address',
      'client_id' => $client->id,
      'latitude' => 13.067439,
      'longitude' => 80.237617,
    ]);
  }

  private function seedNoticeBoardData()
  {
    Notice::create([
      'title' => 'Notice 1',
      'description' => 'Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry\'s standard dummy text ever since the 1500s, when an unknown printer took a galley of type and scrambled it to make a type specimen book. ',
      'notice_for' => 'all',
    ]);

    Notice::create([
      'title' => 'Notice 2',
      'description' => 'Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry\'s standard dummy text ever since the 1500s. ',
      'notice_for' => 'all',
    ]);

    Notice::create([
      'title' => 'Notice 3',
      'description' => 'Contrary to popular belief, Lorem Ipsum is not simply random text. It has roots in a piece of classical Latin literature from 45 BC, making it over 2000 years old. Richard McClintock, a Latin professor at Hampden-Sydney College in Virginia, looked up one of the more obscure Latin words, consectetur, from a Lorem Ipsum passage, and going through the cites of the word in classical literature, discovered the undoubtable source. Lorem Ipsum comes from sections 1.10.32 and 1.10.33 of "de Finibus Bonorum et Malorum" (The Extremes of Good and Evil) by Cicero, ',
      'notice_for' => 'all',
    ]);


  }

  private function seedAttendancesAndLogs(): void
  {
    Log::info('Seeding attendances and attendance logs...');

    $shifts = Shift::all();
    $users = User::all();
    $currentMonth = now()->format('Y-m');
    $currentDate = now()->toDateString();
    foreach ($users as $user) {
      // Loop through all days of the current month
      for ($day = 1; $day <= now()->daysInMonth; $day++) {
        $date = Carbon::createFromFormat('Y-m-d', "$currentMonth-$day");

        // if ($date->isWeekend()) {
        //     continue;
        // }

        $attendance = Attendance::create([
          'user_id' => $user->id,
          'check_in_time' => $date->copy()->setTime(8, rand(0, 59)),
          'check_out_time' => $date->copy()->setTime(18, rand(0, 59)),
          'late_reason' => fake()->optional()->sentence,
          'shift_id' => 1,
          'early_checkout_reason' => fake()->optional()->sentence,
          'notes' => fake()->optional()->sentence,
          'status' => 'present',
          'site_id' => null,
          'approved_by_id' => null,
          'approved_at' => null,
          'created_by_id' => $user->id,
          'updated_by_id' => $user->id,
          'created_at' => $date->startOfDay(),
          'updated_at' => $date->startOfDay(),
        ]);

        // Generate exact logs for the day
        $logTimes = [
          ['type' => 'check_in', 'time' => $date->copy()->setTime(8, rand(0, 59))],
          ['type' => 'break_start', 'time' => $date->copy()->setTime(10, rand(0, 59))],
          ['type' => 'break_end', 'time' => $date->copy()->setTime(10, rand(30, 59))],
          ['type' => 'check_out', 'time' => $date->copy()->setTime(11, rand(0, 59))],
          ['type' => 'check_in', 'time' => $date->copy()->setTime(12, rand(0, 59))],
          ['type' => 'break_start', 'time' => $date->copy()->setTime(13, rand(0, 29))],
          ['type' => 'break_end', 'time' => $date->copy()->setTime(13, rand(30, 59))],
          ['type' => 'break_start', 'time' => $date->copy()->setTime(15, rand(0, 29))],
          ['type' => 'break_end', 'time' => $date->copy()->setTime(15, rand(30, 59))],
          ['type' => 'check_out', 'time' => $date->copy()->setTime(18, rand(0, 59))],
        ];

        foreach ($logTimes as $log) {
          AttendanceLog::create([
            'attendance_id' => $attendance->id,
            'type' => $log['type'],
            'shift_id' => 1,
            'latitude' => fake()->latitude,
            'longitude' => fake()->longitude,
            'altitude' => fake()->optional()->randomFloat(2, 0, 500),
            'speed' => fake()->optional()->randomFloat(2, 0, 100),
            'speedAccuracy' => fake()->optional()->randomFloat(2, 0, 5),
            'horizontalAccuracy' => fake()->optional()->randomFloat(2, 0, 10),
            'verticalAccuracy' => fake()->optional()->randomFloat(2, 0, 10),
            'course' => fake()->optional()->randomFloat(2, 0, 360),
            'courseAccuracy' => fake()->optional()->randomFloat(2, 0, 10),
            'address' => fake()->optional()->address,
            'notes' => fake()->optional()->sentence,
            'created_by_id' => $user->id,
            'updated_by_id' => $user->id,
            'created_at' => $log['time'],
            'updated_at' => $log['time'],
          ]);
        }
      }
    }

    Log::info('Attendances and attendance logs seeding completed.');
  }


}
