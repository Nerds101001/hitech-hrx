<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Site;
use App\Models\User;
use App\Models\Team;
use App\Models\Shift;
use App\Enums\UserAccountStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class RealDemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding Real Demo Organizational Data...');

        // 1. Ensure Team, Shift, and Client exist
        $team = Team::firstOrCreate(['code' => 'DEMO-TEAM'], [
            'name' => 'Main Operations Team',
            'status' => 'active',
            'is_chat_enabled' => true,
            'tenant_id' => 1
        ]);

        $shift = Shift::firstOrCreate(['code' => 'STD-0918'], [
            'name' => 'Standard Shift (09-18)',
            'status' => 'active',
            'start_time' => '09:00:00',
            'end_time' => '18:00:00',
            'start_date' => '2020-01-01',
            'is_default' => true,
            'monday' => true, 'tuesday' => true, 'wednesday' => true, 'thursday' => true, 'friday' => true, 'saturday' => true, 'sunday' => false,
            'tenant_id' => 1
        ]);


        $client = Client::firstOrCreate(['email' => 'corporate@rustx.com'], [
            'name' => 'Hitech Group',
            'address' => 'Corporate Greens, Gurugram',
            'phone' => '9812345678',
            'status' => 'active'
        ]);

        // 2. Units (Sites)
        $unitsData = [
            ['name' => 'Unit I', 'address' => 'B-31, Opp. Power House (Jamalpur), Chandigarh Road, Ludhiana, 141010', 'lat' => 30.901, 'long' => 75.857],
            ['name' => 'Unit II', 'address' => '7 Arkose Ind Estate, Khopoli, Mumbai', 'lat' => 18.789, 'long' => 73.351],
            ['name' => 'Unit III', 'address' => 'Adj. Airport, Vill-Khagat, G.T. Road, Sahnewal, Ludhiana-141120', 'lat' => 30.849, 'long' => 75.955],
            ['name' => 'Unit IV', 'address' => 'Plot No.99, Sector-5, N.H.-8, IMT Manesar Gurgaon- 122050', 'lat' => 28.351, 'long' => 76.928],
            ['name' => 'Unit V', 'address' => 'Plot No.18 Sector-6, IMT Manesar, Gurgaon', 'lat' => 28.355, 'long' => 76.932],
            ['name' => 'Unit VI', 'address' => 'Plot No.797, Thiruvallur High Road, Andersenpet, Thiruvallur, Chennai- 600124', 'lat' => 13.143, 'long' => 79.912],
            ['name' => 'Unit VII', 'address' => 'Plot No.E-133 & E-134, Addl. Patalgana Industrial Area Vill-Chavane', 'lat' => 18.847, 'long' => 73.148],
            ['name' => 'Unit VIII', 'address' => 'Sahnewal Airport Road, Ramgarh Road, Ramgarh, Sahnewal, Ludhiana, Punjab- 141123', 'lat' => 30.845, 'long' => 75.960],
            ['name' => 'Unit IX', 'address' => 'Plot No. 146-147, Nandanvan Indsutrial Estate, Vill. Bakrol Bujrang, Tal: Daskroi, Distt. Ahmadabad 382431', 'lat' => 22.986, 'long' => 72.684],
            ['name' => 'CG Office', 'address' => 'Corporate Greens, Gurugram, Haryana', 'lat' => 28.375, 'long' => 76.960],
        ];

        $sites = [];
        foreach ($unitsData as $data) {
            $sites[$data['name']] = Site::firstOrCreate(['name' => $data['name']], [
                'address' => $data['address'],
                'latitude' => $data['lat'],
                'longitude' => $data['long'],
                'radius' => 500,
                'status' => 'active',
                'is_attendance_enabled' => true,
                'attendance_type' => 'geofence',
                'shift_id' => $shift->id,
                'client_id' => $client->id
            ]);
        }

        // 3. Departments
        $financeDept = Department::firstOrCreate(['name' => 'Finance'], ['code' => 'FIN', 'tenant_id' => 1]);
        $purchaseDept = Department::firstOrCreate(['name' => 'Purchase'], ['code' => 'PUR', 'tenant_id' => 1]);
        $accountsDept = Department::firstOrCreate(['name' => 'Accounts'], ['code' => 'ACC', 'tenant_id' => 1]);
        $exportDept = Department::firstOrCreate(['name' => 'Export'], ['code' => 'EXP', 'tenant_id' => 1]);
        $bizDevDept = Department::firstOrCreate(['name' => 'Business Development'], ['code' => 'BD', 'tenant_id' => 1]);
        $custCareDept = Department::firstOrCreate(['name' => 'Customer Care'], ['code' => 'CC', 'tenant_id' => 1]);
        $custExcelDept = Department::firstOrCreate(['name' => 'Customer Excellence'], ['code' => 'CX', 'tenant_id' => 1]);
        $presalesDept = Department::firstOrCreate(['name' => 'Presales'], ['code' => 'PS', 'tenant_id' => 1]);

        // 4. Designations
        $financeHead = Designation::firstOrCreate(['name' => 'Finance Head', 'department_id' => $financeDept->id], ['code' => 'FIN-H']);
        $financeExec = Designation::firstOrCreate(['name' => 'Finance Executive', 'department_id' => $financeDept->id], ['code' => 'FIN-E']);
        
        $purchaseManager = Designation::firstOrCreate(['name' => 'Manager Purchase', 'department_id' => $purchaseDept->id], ['code' => 'PUR-M']);
        $purchaseExec = Designation::firstOrCreate(['name' => 'Purchase Executive', 'department_id' => $purchaseDept->id], ['code' => 'PUR-E']);
        
        $hodAccounts = Designation::firstOrCreate(['name' => 'HOD Accounts', 'department_id' => $accountsDept->id], ['code' => 'ACC-HOD']);
        $srManagerAccounts = Designation::firstOrCreate(['name' => 'Sr. Manager Accounts', 'department_id' => $accountsDept->id], ['code' => 'ACC-SRM']);
        $managerAccounts = Designation::firstOrCreate(['name' => 'Manager Accounts', 'department_id' => $accountsDept->id], ['code' => 'ACC-M']);
        $accountsExec = Designation::firstOrCreate(['name' => 'Accounts Executive', 'department_id' => $accountsDept->id], ['code' => 'ACC-E']);
        $jrAccountsExec = Designation::firstOrCreate(['name' => 'Jr. Executive Time Office', 'department_id' => $accountsDept->id], ['code' => 'ACC-JR']);

        $dirBusDev = Designation::firstOrCreate(['name' => 'Director Business Development', 'department_id' => $bizDevDept->id], ['code' => 'BD-D']);
        $managerCustCare = Designation::firstOrCreate(['name' => 'Manager Customer Care', 'department_id' => $custCareDept->id], ['code' => 'CC-M']);
        $managerCustExcel = Designation::firstOrCreate(['name' => 'Manager Customer Excellence', 'department_id' => $custExcelDept->id], ['code' => 'CX-M']);
        $srSpclstBusDev = Designation::firstOrCreate(['name' => 'Sr. Specialist Business Development', 'department_id' => $bizDevDept->id], ['code' => 'BD-SS']);
        $asstManagerPresales = Designation::firstOrCreate(['name' => 'Asst. Manager Presales', 'department_id' => $presalesDept->id], ['code' => 'PS-AM']);
        $managerAI = Designation::firstOrCreate(['name' => 'Manager AI', 'department_id' => $custExcelDept->id], ['code' => 'CX-AI']);
        $srExecNBD = Designation::firstOrCreate(['name' => 'Sr. Executive NBD', 'department_id' => $bizDevDept->id], ['code' => 'BD-NBD']);
        $custCareExec = Designation::firstOrCreate(['name' => 'Customer Care Executive', 'department_id' => $custCareDept->id], ['code' => 'CC-E']);
        $srExecCustCare = Designation::firstOrCreate(['name' => 'Sr. Executive Customer Care', 'department_id' => $custCareDept->id], ['code' => 'CC-SE']);

        $dirExport = Designation::firstOrCreate(['name' => 'Director Export', 'department_id' => $exportDept->id], ['code' => 'EXP-D']);
        $managerExport = Designation::firstOrCreate(['name' => 'Manager Export', 'department_id' => $exportDept->id], ['code' => 'EXP-M']);
        $exportExec = Designation::firstOrCreate(['name' => 'Export Executive', 'department_id' => $exportDept->id], ['code' => 'EXP-E']);

        // 5. Employees

        // --- FINANCE ---
        $arpana = $this->createUser('Arpana Katyal', 'finance@doctorrust.com', $financeHead, $sites['Unit IV'], $shift, $team);
        $this->createUser('Lata', 'lata@rustx.com', $financeExec, $sites['Unit II'], $shift, $team, $arpana->id);
        $this->createUser('Ruchi Garg', 'ruchi@rustx.com', $financeExec, $sites['Unit IV'], $shift, $team, $arpana->id);
        $this->createUser('Priyanka Kapur', 'priyanka.k@rustx.com', $financeExec, $sites['Unit II'], $shift, $team, $arpana->id);
        $this->createUser('Sandhya', 'sandhya@rustx.com', $financeExec, $sites['Unit IV'], $shift, $team, $arpana->id);
        $this->createUser('Shavi Goyal', 'shavi@rustx.com', $financeExec, $sites['Unit IV'], $shift, $team, $arpana->id);

        // --- PURCHASE ---
        $nitasha = $this->createUser('Nitasha', 'purchase@doctorrust.com', $purchaseManager, $sites['Unit IV'], $shift, $team);
        $this->createUser('Sheetal', 'sheetal@rustx.com', $purchaseExec, $sites['Unit III'], $shift, $team, $nitasha->id);
        $this->createUser('Meha', 'meha@rustx.com', $purchaseExec, $sites['Unit IV'], $shift, $team, $nitasha->id);
        $this->createUser('Shweta', 'shweta@rustx.com', $purchaseExec, $sites['Unit IV'], $shift, $team, $nitasha->id);
        $this->createUser('Priyanka', 'priyanka@rustx.com', $purchaseExec, $sites['Unit VI'], $shift, $team, $nitasha->id);
        $this->createUser('Ankita', 'ankita@rustx.com', $purchaseExec, $sites['Unit IV'], $shift, $team, $nitasha->id);
        $this->createUser('Anjali', 'anjali@rustx.com', $purchaseExec, $sites['Unit IX'], $shift, $team, $nitasha->id);

        // --- ACCOUNTS ---
        $rajesh = $this->createUser('Rajesh Kumar', 'accounts@doctorrust.com', $hodAccounts, $sites['Unit I'], $shift, $team);
        $rajiv = $this->createUser('Rajiv Grover', 'rajiv@rustx.com', $srManagerAccounts, $sites['Unit I'], $shift, $team);
        $shubham = $this->createUser('Shubham Kumar', 'accounts5@rustx.net', $accountsExec, $sites['Unit I'], $shift, $team, $rajesh->id);
        $dharminder = $this->createUser('Dharminder Pal', 'accounts2@rustx.net', $managerAccounts, $sites['Unit I'], $shift, $team, $rajiv->id);

        foreach(['Raj Kishore', 'Shammi', 'Ritu Kamal', 'Manisha'] as $name) {
            $this->createUser($name, strtolower(str_replace(' ', '.', $name)) . '@rustx.com', $accountsExec, $sites['Unit I'], $shift, $team, $shubham->id);
        }
        foreach(['Jatinder', 'Navtej', 'Divya', 'Shashi Gautam', 'Rupali Arora'] as $name) {
            $this->createUser($name, strtolower(str_replace(' ', '.', $name)) . '@rustx.com', $accountsExec, $sites['Unit I'], $shift, $team, $dharminder->id);
        }

        // --- CG OFFICE TEAM + EXPORT ---
        $sidharth = $this->createUser('Sidharth Sareen', 'sidharthsareen@doctorrust.com', $dirExport, $sites['CG Office'], $shift, $team);
        $mukul = $this->createUser('Mukul Sareen', 'mukulsareen@doctorrust.com', $dirBusDev, $sites['CG Office'], $shift, $team);

        // CG Office - Subordinates of Mukul
        $mamta = $this->createUser('Mamta Arora', 'ccare2@drbio.in', $managerCustCare, $sites['CG Office'], $shift, $team, $mukul->id);
        $sobika = $this->createUser('Sobika Ambardar', 'ce@drbio.in', $managerCustExcel, $sites['CG Office'], $shift, $team, $mukul->id);

        // Mamta's Team
        $mamtaSubordinates = [
            ['Deepali Sharma', 'newbiz6@drbio.in', $srSpclstBusDev],
            ['Geet Kaur', 'newbiz10@drbio.in', $srSpclstBusDev],
            ['Sonia Arora', 'Newbiz10@rustx.com', $srSpclstBusDev],
            ['Shivani Chibber', 'ccare12@drbio.in', $custCareExec],
            ['Rekha Rajput', 'newbiz3@rustx.com', $srSpclstBusDev],
            ['Pooja Soni', 'Ccare2@fillezy.com', $srExecCustCare],
            ['Rajendra Singh', 'ccare11@rustx.com', $custCareExec],
            ['Pooja Kumari', 'ccare3@fillezy.com', $custCareExec],
        ];
        foreach($mamtaSubordinates as $sub) {
            $this->createUser($sub[0], $sub[1], $sub[2], $sites['CG Office'], $shift, $team, $mamta->id);
        }

        // Sobika's Team
        $sobikaSubordinates = [
            ['Trishna Dhariwal', 'Presales2@hitechgroup.in', $asstManagerPresales],
            ['Ratna Kumari', 'Presales1@hitechgroup.in', $asstManagerPresales],
            ['Meenu Kohli', 'ai@rustx.com', $managerAI],
            ['Shruti Mittal', 'export6@rustx.com', $srExecNBD],
        ];
        foreach($sobikaSubordinates as $sub) {
            $this->createUser($sub[0], $sub[1], $sub[2], $sites['CG Office'], $shift, $team, $sobika->id);
        }

        // Export Subordinates of Sidharth
        $this->createUser('Alex Raj Saini', 'export2@rustxusa.com', $managerExport, $sites['Unit I'], $shift, $team, $sidharth->id);
        $this->createUser('Manjinder Kaur', 'export5@rustxusa.com', $managerExport, $sites['Unit I'], $shift, $team, $sidharth->id);

        $this->command->info('Demo data seeded successfully!');
    }

    private function createUser($fullName, $email, $designation, $site, $shift, $team, $reportingToId = null)
    {
        $names = explode(' ', $fullName, 2);
        $firstName = $names[0];
        $lastName = isset($names[1]) ? $names[1] : '';

        $user = User::firstOrCreate(['email' => $email], [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'password' => Hash::make('123456'),
            'status' => UserAccountStatus::ACTIVE,
            'code' => 'EMP-' . strtoupper(Str::random(5)),
            'designation_id' => $designation->id,
            'site_id' => $site->id,
            'shift_id' => $shift->id,
            'team_id' => $team->id,
            'reporting_to_id' => $reportingToId,
            'date_of_joining' => now()->subYears(rand(1, 4)),
            'base_salary' => rand(30000, 90000),
            'tenant_id' => 1
        ]);


        $roleName = str_contains(strtolower($designation->name), 'manager') || str_contains(strtolower($designation->name), 'head') || str_contains(strtolower($designation->name), 'director') ? 'manager' : 'employee';
        
        $user->assignRole($roleName);


        return $user;
    }
}
