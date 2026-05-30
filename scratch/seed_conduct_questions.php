<?php
require 'd:/live_server/vendor/autoload.php';
$app = require_once 'd:/live_server/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\TrainingModule;
use App\Models\TrainingQuestion;

/*
 * This script:
 * 1. Adds 30 MCQ questions to Module ID 1 (Company Code of Conduct)
 * 2. Sets questions_per_test = 10 on that module (round-robin random pool of 30)
 */

$moduleId = 1; // Company Code of Conduct

// Set questions_per_test = 10
TrainingModule::where('id', $moduleId)->update(['questions_per_test' => 10]);

// 30 high-quality HR / Code-of-Conduct MCQs
$questions = [
    [
        'question' => 'What does a Code of Conduct primarily establish?',
        'options' => ['Company dress code policies', 'Expected standards of behaviour and ethics for all employees', 'Salary structures and increments', 'Attendance and leave rules'],
        'correct_option_index' => 1,
        'marks' => 1,
    ],
    [
        'question' => 'Which of the following is an example of a conflict of interest?',
        'options' => ['Arriving to work on time', 'Reporting a colleague\'s misconduct', 'Owning shares in a direct competitor without disclosure', 'Taking approved annual leave'],
        'correct_option_index' => 2,
        'marks' => 1,
    ],
    [
        'question' => 'What should an employee do if they witness workplace harassment?',
        'options' => ['Ignore it, as it is not their concern', 'Laugh along with colleagues', 'Report it to HR or a designated authority immediately', 'Discuss it openly with all coworkers'],
        'correct_option_index' => 2,
        'marks' => 1,
    ],
    [
        'question' => 'Confidential company information should be:',
        'options' => ['Shared with friends and family for feedback', 'Stored securely and only shared on a need-to-know basis', 'Posted on personal social media for transparency', 'Discussed in public spaces'],
        'correct_option_index' => 1,
        'marks' => 1,
    ],
    [
        'question' => 'Which of the following best describes "professional integrity"?',
        'options' => ['Working long hours regardless of productivity', 'Consistently acting honestly and ethically even when unobserved', 'Agreeing with your manager on all decisions', 'Completing tasks as quickly as possible'],
        'correct_option_index' => 1,
        'marks' => 1,
    ],
    [
        'question' => 'What is the correct action if you receive a gift from a vendor worth ₹10,000?',
        'options' => ['Accept it as a reward for good service', 'Keep it privately without disclosure', 'Declare it to HR/Compliance as per the gifts policy', 'Return it and then secretly accept cash instead'],
        'correct_option_index' => 2,
        'marks' => 1,
    ],
    [
        'question' => 'Which of the following is NOT considered workplace misconduct?',
        'options' => ['Bullying a subordinate', 'Falsifying expense claims', 'Raising a genuine grievance through proper channels', 'Discriminating based on religion'],
        'correct_option_index' => 2,
        'marks' => 1,
    ],
    [
        'question' => 'Social media usage during office hours should be:',
        'options' => ['Unlimited, as it boosts creativity', 'Restricted to official company profiles and job-related purposes', 'Encouraged to attract new talent', 'Completely forbidden at all times'],
        'correct_option_index' => 1,
        'marks' => 1,
    ],
    [
        'question' => 'If an employee disagrees with a company policy, they should:',
        'options' => ['Ignore the policy and work as they see fit', 'Spread dissatisfaction among colleagues', 'Raise the concern through proper feedback channels', 'Leak it to outside media'],
        'correct_option_index' => 2,
        'marks' => 1,
    ],
    [
        'question' => 'What does "equal opportunity employment" mean?',
        'options' => ['All employees earn the same salary', 'Hiring and promotion are based on merit, without discrimination', 'Only local candidates are hired', 'Seniority always determines promotions'],
        'correct_option_index' => 1,
        'marks' => 1,
    ],
    [
        'question' => 'Workplace diversity refers to:',
        'options' => ['Having employees of different age groups only', 'Inclusion of people with varied backgrounds, experiences, and identities', 'Hiring employees from different cities', 'Offering multiple job roles'],
        'correct_option_index' => 1,
        'marks' => 1,
    ],
    [
        'question' => 'Which action best demonstrates accountability?',
        'options' => ['Blaming a coworker for your mistake', 'Acknowledging your error and taking corrective action', 'Hiding a mistake hoping no one notices', 'Delegating all tasks to avoid responsibility'],
        'correct_option_index' => 1,
        'marks' => 1,
    ],
    [
        'question' => 'Company assets (laptops, vehicles, funds) should be used:',
        'options' => ['For personal use when not in office', 'Only for legitimate business purposes', 'Shared freely with family members', 'Taken home permanently after probation'],
        'correct_option_index' => 1,
        'marks' => 1,
    ],
    [
        'question' => 'Which statement about email communication is correct?',
        'options' => ['You can forward internal reports to personal email for convenience', 'Always use professional language and avoid sending confidential data externally', 'Work emails can be used for personal business pitches', 'Reply-all is always the best practice'],
        'correct_option_index' => 1,
        'marks' => 1,
    ],
    [
        'question' => 'A "whistleblower policy" is designed to:',
        'options' => ['Punish employees who speak up', 'Protect employees who report unethical conduct from retaliation', 'Reward employees for public disclosure of company secrets', 'Monitor employee behaviour externally'],
        'correct_option_index' => 1,
        'marks' => 1,
    ],
    [
        'question' => 'What is the primary goal of anti-discrimination policies?',
        'options' => ['To increase paperwork for HR', 'To ensure all employees are treated fairly regardless of personal characteristics', 'To restrict the hiring of certain groups', 'To rank employees by seniority'],
        'correct_option_index' => 1,
        'marks' => 1,
    ],
    [
        'question' => 'Using a colleague\'s login credentials is:',
        'options' => ['Acceptable if they permit it verbally', 'A serious violation of information security policy', 'Fine in emergency situations without documentation', 'Only prohibited for admin accounts'],
        'correct_option_index' => 1,
        'marks' => 1,
    ],
    [
        'question' => 'What does POSH stand for in the Indian workplace context?',
        'options' => ['Prevention of Sexual Harassment', 'Policy on Standard Hiring', 'Protocol on Salary and Hours', 'Prevention of Safety Hazards'],
        'correct_option_index' => 0,
        'marks' => 1,
    ],
    [
        'question' => 'If you accidentally receive a confidential email not meant for you, you should:',
        'options' => ['Forward it to your team to decide what to do', 'Read and store it for future reference', 'Delete it and notify the sender that it was misdirected', 'Share it with your manager immediately'],
        'correct_option_index' => 2,
        'marks' => 1,
    ],
    [
        'question' => 'Which of the following behaviours represents a hostile work environment?',
        'options' => ['Constructive criticism in a performance review', 'Persistent intimidation, verbal abuse, or offensive jokes targeting colleagues', 'Disagreeing professionally during a meeting', 'Setting clear performance expectations'],
        'correct_option_index' => 1,
        'marks' => 1,
    ],
    [
        'question' => 'Intellectual property created during employment belongs to:',
        'options' => ['The individual employee who created it', 'The company, as outlined in the employment agreement', 'The government, under patent laws', 'Whoever publishes it first'],
        'correct_option_index' => 1,
        'marks' => 1,
    ],
    [
        'question' => 'What is the correct response if a client offers you a bribe?',
        'options' => ['Accept it, as it does not affect company operations', 'Politely refuse, and report the incident to management or compliance', 'Ask for it in a different form', 'Ignore it and continue doing business'],
        'correct_option_index' => 1,
        'marks' => 1,
    ],
    [
        'question' => 'Remote work employees are expected to:',
        'options' => ['Work only on personal projects to optimise their time', 'Maintain the same professionalism, availability, and data security as in the office', 'Share company data on personal cloud storage for easy access', 'Opt out of meetings to increase productivity'],
        'correct_option_index' => 1,
        'marks' => 1,
    ],
    [
        'question' => 'Which of the following is a sign of a respectful workplace?',
        'options' => ['Allowing only senior employees to speak in meetings', 'Active listening, inclusive communication, and valuing diverse perspectives', 'Prioritising individual achievement over team collaboration', 'Using formal titles at all times regardless of comfort'],
        'correct_option_index' => 1,
        'marks' => 1,
    ],
    [
        'question' => 'Which statement about data privacy is correct?',
        'options' => ['Customer data can be shared freely within any department', 'Employee and customer data must be handled per applicable data protection laws', 'Saving customer data on personal devices is fine for field work', 'Data privacy only applies to HR and Finance'],
        'correct_option_index' => 1,
        'marks' => 1,
    ],
    [
        'question' => 'An employee who suspects financial fraud in the organisation should:',
        'options' => ['Handle it privately to avoid embarrassment', 'Report it through the official whistleblower or grievance channel', 'Discuss it openly in the break room', 'Ignore it unless directly affected'],
        'correct_option_index' => 1,
        'marks' => 1,
    ],
    [
        'question' => 'Disciplinary action may be taken against an employee for:',
        'options' => ['Requesting a promotion', 'Submitting a leave application', 'Violating company policy, misconduct, or insubordination', 'Participating in a company event'],
        'correct_option_index' => 2,
        'marks' => 1,
    ],
    [
        'question' => 'Why is punctuality important in a professional environment?',
        'options' => ['It is only important for entry-level employees', 'It demonstrates respect for colleagues\' time and commitment to responsibilities', 'It increases salary automatically', 'It is only required for client-facing roles'],
        'correct_option_index' => 1,
        'marks' => 1,
    ],
    [
        'question' => 'The purpose of a formal grievance procedure is to:',
        'options' => ['Create conflict between employees and management', 'Provide a structured process for resolving workplace complaints fairly', 'Allow employees to sue the company publicly', 'Assign blame to team leaders'],
        'correct_option_index' => 1,
        'marks' => 1,
    ],
    [
        'question' => 'Which of the following is a healthy workplace habit?',
        'options' => ['Overworking without breaks to impress management', 'Setting clear boundaries, communicating proactively, and taking required leave', 'Skipping lunch to finish tasks faster', 'Avoiding feedback from peers'],
        'correct_option_index' => 1,
        'marks' => 1,
    ],
];

// Delete old questions for this module before re-seeding
TrainingQuestion::where('module_id', $moduleId)->delete();

$inserted = 0;
foreach ($questions as $q) {
    TrainingQuestion::create([
        'module_id'            => $moduleId,
        'question'             => $q['question'],
        'options'              => $q['options'],
        'correct_option_index' => $q['correct_option_index'],
        'marks'                => $q['marks'],
        'tenant_id'            => null,
    ]);
    $inserted++;
}

echo "✅  Inserted {$inserted} questions into Module ID {$moduleId} (Company Code of Conduct)\n";
echo "✅  Set questions_per_test = 10 on that module\n";
echo "   (10 questions will be drawn at random from the pool of 30 for each attempt)\n";
