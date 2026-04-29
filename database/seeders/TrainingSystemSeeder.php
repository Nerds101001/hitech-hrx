<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TrainingPhase;
use App\Models\TrainingModule;
use App\Models\TrainingQuestion;
use App\Models\User;

class TrainingSystemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Create Phases
        $phase1 = TrainingPhase::create([
            'title' => 'Phase 1: Culture & Integrity',
            'description' => 'Learn about our core values, mission, and the rules that keep us great.',
            'order' => 1
        ]);

        $phase2 = TrainingPhase::create([
            'title' => 'Phase 2: Product Knowledge',
            'description' => 'Deep dive into our product catalogs and the value we provide to customers.',
            'order' => 2
        ]);

        $phase3 = TrainingPhase::create([
            'title' => 'Phase 3: Operational Excellence',
            'description' => 'Master the tools and workflows you will use daily.',
            'order' => 3
        ]);

        // 2. Create Modules for Phase 1
        $module1 = TrainingModule::create([
            'phase_id' => $phase1->id,
            'title' => 'Company Code of Conduct',
            'description' => 'Understanding our professional standards and ethical guidelines.',
            'content_type' => 'policy',
            'content_body' => "Welcome to the Team! We believe in transparency, respect, and hard work.\n\nOur first rule is simple: Always act with integrity. Whether you are dealing with a client or a colleague, honesty is our cornerstone.\n\nPunctuality and Professionalism: We value your time. Being on time means you respect your work and your teammates.\n\nDiversity and Inclusion: We are a global family. We celebrate differences and provide equal opportunities for everyone to grow.",
            'estimated_time_minutes' => 15,
            'order' => 1
        ]);

        // Questions for Module 1
        TrainingQuestion::create([
            'module_id' => $module1->id,
            'question' => 'What is the cornerstone of our professional standards?',
            'options' => ['Speed', 'Honesty & Integrity', 'Low Cost', 'Isolation'],
            'correct_option_index' => 1
        ]);

        TrainingQuestion::create([
            'module_id' => $module1->id,
            'question' => 'What does being on time signify in our company?',
            'options' => ['Nothing', 'Strict management', 'Respect for work and teammates', 'Lack of freedom'],
            'correct_option_index' => 2
        ]);

        // 3. Create Modules for Phase 2
        $module2 = TrainingModule::create([
            'phase_id' => $phase2->id,
            'title' => 'Product Catalog: HRX Core',
            'description' => 'Learn about our primary HR management suite features.',
            'content_type' => 'catalog',
            'content_body' => "HRX Core is our flagship product. It handles everything from attendance to payroll.\n\nFeature 1: Automated Payroll. No more manual calculations. Our system syncs with attendance logs to generate accurate payslips.\n\nFeature 2: Employee Lifecycle. Track an employee from recruitment to retirement seamlessly.\n\nFeature 3: AI-Powered Analytics. Get insights into team performance and turnover rates with a single click.",
            'estimated_time_minutes' => 30,
            'order' => 1
        ]);

        // Questions for Module 2
        TrainingQuestion::create([
            'module_id' => $module2->id,
            'question' => 'Which feature handles recruitment to retirement tracking?',
            'options' => ['Payroll', 'Lifecycle Management', 'Analytics', 'Attendance'],
            'correct_option_index' => 1
        ]);

        // 4. Create Modules for Phase 3 (Video)
        TrainingModule::create([
            'phase_id' => $phase3->id,
            'title' => 'Welcome Message from CEO',
            'description' => 'A personal welcome and vision statement from our leadership.',
            'content_type' => 'video',
            'content_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', // Placeholder
            'estimated_time_minutes' => 5,
            'order' => 1
        ]);

        // 5. Tag an existing user for training (for testing)
        $user = User::first();
        if ($user) {
            $user->update([
                'is_training_required' => true,
                'training_status' => 'not_started'
            ]);
        }
    }
}
