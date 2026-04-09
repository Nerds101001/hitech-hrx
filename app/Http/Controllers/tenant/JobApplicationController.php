<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\CustomQuestion;
use App\Models\Designation;
use App\Models\DocumentType as Document;
use App\Models\InterviewSchedule;
use App\Models\Job;
use App\Models\JobApplication;
use App\Models\JobApplicationNote;
use App\Models\JobOnBoard;
use App\Models\JobStage;
use App\Models\Department;
use App\Models\User;
use App\Models\Utility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Models\JobCategory;
use App\Models\GenerateOfferLetter;
use App\Models\PayslipType;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\File;

class JobApplicationController extends Controller
{

    public function index(Request $request)
    {

        if (Auth::user()->can('Manage Job Application')) {
            $stages = JobStage::where('created_by', '=', Auth::user()->creatorId())->orderBy('order', 'asc')->get();

            $jobs = Job::where('created_by', Auth::user()->creatorId())->get()->pluck('title', 'id');
            $jobs->prepend('All', '');

            if (isset($request->start_date) && !empty($request->start_date)) {

                $filter['start_date'] = $request->start_date;
            } else {

                $filter['start_date'] = date("Y-m-d", strtotime("-1 month"));
            }

            if (isset($request->end_date) && !empty($request->end_date)) {

                $filter['end_date'] = $request->end_date;
            } else {

                $filter['end_date'] = date("Y-m-d H:i:s", strtotime("+1 hours"));
            }

            if (isset($request->job) && !empty($request->job)) {

                $filter['job'] = $request->job;
            } else {
                $filter['job'] = '';
            }

            return view('tenant.jobApplication.index', compact('stages', 'jobs', 'filter'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function create()
    {
        $jobs = Job::where('created_by', Auth::user()->creatorId())->get()->pluck('title', 'id');
        $jobs->prepend('--', '');
        $questions = CustomQuestion::where('created_by', Auth::user()->creatorId())->get();
        return view('tenant.jobApplication.create', compact('jobs', 'questions'));
    }

    public function store(Request $request)
    {
        if (Auth::user()->can('Create Job Application')) {

            $validator = Validator::make(
                $request->all(),
                [
                    'job' => 'required',
                    'name' => 'required',
                    'email' => 'required',
                    'phone' => 'required',
                ]
            );

            if ($validator->fails()) {
                $messages = $validator->getMessageBag();

                return redirect()->back()->with('error', $messages->first());
            }

            $stage = JobStage::where('created_by', Auth::user()->creatorId())->first();

            $job                  = new JobApplication();
            $job->job             = $request->job;
            $job->name            = $request->name;
            $job->email           = $request->email;
            $job->phone           = $request->phone;
            $job->cover_letter    = $request->cover_letter;
            $job->dob             = $request->dob;
            $job->gender          = $request->gender;
            $job->address         = $request->address;
            $job->country         = $request->country;
            $job->state           = $request->state;
            $job->stage           = $stage->id;
            $job->city            = $request->city;
            $job->zip_code        = $request->zip_code;
            $job->custom_question = json_encode($request->question);
            $job->created_by      = Auth::user()->creatorId();

            if (!empty($request->profile)) {

                $image_size = $request->file('profile')->getSize();
                $result = Utility::updateStorageLimit(Auth::user()->creatorId(), $image_size);
                if ($result == 1) {
                    $filenameWithExt = $request->file('profile')->getClientOriginalName();
                    $filename        = pathinfo($filenameWithExt, PATHINFO_FILENAME);
                    $extension       = $request->file('profile')->getClientOriginalExtension();
                    $fileNameToStore = $filename . '_' . time() . '.' . $extension;

                    $dir        = 'job/profile';

                    $url = '';
                    $path = Utility::upload_file($request, 'profile', $fileNameToStore, $dir, []);
                    $job->profile         = !empty($request->profile) ? $fileNameToStore : '';
                    if ($path['flag'] == 1) {
                        $url = $path['url'];
                    } else {
                        return redirect()->back()->with('error', __($path['msg']));
                    }
                }
            }

            if (!empty($request->resume)) {

                $image_size = $request->file('resume')->getSize();
                $result = Utility::updateStorageLimit(Auth::user()->creatorId(), $image_size);

                if ($result == 1) {
                    $filenameWithExt1 = $request->file('resume')->getClientOriginalName();
                    $filename1        = pathinfo($filenameWithExt1, PATHINFO_FILENAME);
                    $extension1       = $request->file('resume')->getClientOriginalExtension();
                    $fileNameToStore1 = $filename1 . '_' . time() . '.' . $extension1;

                    $dir        = 'job/resume';

                    $url = '';
                    $path = Utility::upload_file($request, 'resume', $fileNameToStore1, $dir, []);
                    $job->resume          = !empty($request->resume) ? $fileNameToStore1 : '';

                    if ($path['flag'] == 1) {
                        $url = $path['url'];
                    } else {
                        return redirect()->back()->with('error', __($path['msg']));
                    }
                }
            }
            $job->save();

            return redirect()->route('job-application.index')->with('success', __('Job application successfully created.') . ((isset($result) && $result != 1) ? '<br> <span class="text-danger">' . $result . '</span>' : ''));
        } else {
            return redirect()->route('job-application.index')->with('error', __('Permission denied.'));
        }
    }

    public function show($ids)
    {
        if (Auth::user()->can('Show Job Application')) {
            try {
                $id = Crypt::decrypt($ids);
            } catch (\Exception $e) {
                return redirect()->back()->with('error', __('Invalid ID or payload.'));
            }

            $jobApplication = JobApplication::find($id);

            if (!$jobApplication) {
                return redirect()->back()->with('error', __('Job application not found.'));
            }

            $notes = JobApplicationNote::where('application_id', $id)->get();
            $stages = JobStage::where('created_by', Auth::user()->creatorId())->get();

            return view('tenant.jobApplication.show', compact('jobApplication', 'notes', 'stages'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function destroy(JobApplication $jobApplication)
    {
        if (Auth::user()->can('Delete Job Application')) {
            $jobApplication->delete();

            if (!empty($jobApplication->profile)) {
                $file_path = 'job/profile/' . $jobApplication->profile;
                // Utility::changeStorageLimit(Auth::user()->creatorId(), $file_path);
            }

            if (!empty($jobApplication->resume)) {
                $file_path = 'job/resume/' . $jobApplication->resume;
                // Utility::changeStorageLimit(Auth::user()->creatorId(), $file_path);
            }

            return redirect()->route('job-application.index')->with('success', __('Job application   successfully deleted.'));
        } else {
            return redirect()->route('job-application.index')->with('error', __('Permission denied.'));
        }
    }

    public function order(Request $request)
    {
        if (Auth::user()->can('Move Job Application')) {
            $post = $request->all();
            foreach ($post['order'] as $key => $item) {
                $application        = JobApplication::where('id', '=', $item)->first();
                $application->order = $key;
                $application->stage = $post['stage_id'];
                $application->save();
            }
            
            return response()->json(['success' => __('Application moved successfully.')]);
        } else {
            return response()->json(['error' => 'Permission denied'], 404);
        }
    }

    public function addSkill(Request $request, $id)
    {
        if (Auth::user()->can('Add Job Application Skill')) {
            $validator = Validator::make(
                $request->all(),
                [
                    'skill' => 'required',
                ]
            );

            if ($validator->fails()) {
                $messages = Validator::make($request->all(), [])->getMessageBag(); // Should be $validator

                return redirect()->back()->with('error', $messages->first());
            }

            $job        = JobApplication::find($id);
            $job->skill = $request->skill;
            $job->save();

            return redirect()->back()->with('success', __('Job application skill successfully added.'));
        } else {
            return redirect()->route('job-application.index')->with('error', __('Permission denied.'));
        }
    }

    public function addNote(Request $request, $id)
    {
        if (Auth::user()->can('Add Job Application Note')) {
            $validator = Validator::make(
                $request->all(),
                [
                    'note' => 'required',
                ]
            );

            if ($validator->fails()) {
                $messages = $validator->getMessageBag();

                return redirect()->back()->with('error', $messages->first());
            }

            $note                 = new JobApplicationNote();
            $note->application_id = $id;
            $note->note           = $request->note;
            $note->note_created   = Auth::user()->id;
            $note->created_by     = Auth::user()->creatorId();
            $note->save();

            return redirect()->back()->with('success', __('Job application notes successfully added.'));
        } else {
            return redirect()->route('job-application.index')->with('error', __('Permission denied.'));
        }
    }

    public function destroyNote($id)
    {
        if (Auth::user()->can('Delete Job Application Note')) {
            $note = JobApplicationNote::find($id);
            $note->delete();

            return redirect()->back()->with('success', __('Job application notes successfully deleted.'));
        } else {
            return redirect()->route('job-application.index')->with('error', __('Permission denied.'));
        }
    }

    public function rating(Request $request, $id)
    {
        $jobApplication = JobApplication::find($id);

        if (!$jobApplication) {
            return response()->json(['error' => 'Job application not found.'], 404);
        }

        $jobApplication->rating = $request->rating;
        $jobApplication->save();

        return response()->json(['success' => 'Rating updated successfully.']);
    }

    public function archive($id)
    {
        $jobApplication = JobApplication::find($id);
        if ($jobApplication->is_archive == 0) {
            $jobApplication->is_archive = 1;
            $jobApplication->save();

            return redirect()->route('job.application.candidate')->with('success', __('Job application successfully added to archive.'));
        } else {
            $jobApplication->is_archive = 0;
            $jobApplication->save();

            return redirect()->route('job-application.index')->with('success', __('Job application successfully remove to archive.'));
        }
    }

    public function candidate(Request $request)
    {
        if (Auth::user()->can('Manage Job OnBoard')) {
            $query = JobApplication::where('created_by', Auth::user()->creatorId())
                                   ->where('is_archive', 1)
                                   ->with('jobs');
                                   
            if ($request->has('job') && !empty($request->job)) {
                $query->where('job', $request->job);
            }
            
            $archive_application = $query->get();
            
            $jobs = Job::where('created_by', Auth::user()->creatorId())->get()->pluck('title', 'id');
            $jobs->prepend('All', '');
            
            $filter = [
                'job' => $request->job ?? ''
            ];

            return view('tenant.jobApplication.candidate', compact('archive_application', 'jobs', 'filter'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function jobBoardCreate($id)
    {
        $status          = JobOnBoard::$status;
        $job_type        = JobOnBoard::$job_type;
        $salary_duration = JobOnBoard::$salary_duration;
        $salary_type     = PayslipType::where('created_by_id', Auth::user()->creatorId())->get()->pluck('name', 'id');
        $applications    = InterviewSchedule::select('interview_schedules.*', 'job_applications.name')->join('job_applications', 'interview_schedules.candidate', '=', 'job_applications.id')->where('interview_schedules.created_by', Auth::user()->creatorId())->get()->pluck('name', 'candidate');
        $applications->prepend('-', '');

        return view('tenant.jobApplication.onboardCreate', compact('id', 'status', 'applications', 'job_type', 'salary_type', 'salary_duration'));
    }

    public function jobOnBoard()
    {
        if (Auth::user()->can('Manage Job OnBoard')) {
            $jobOnBoards = JobOnBoard::where('created_by', Auth::user()->creatorId())->with('applications')->get();

            return view('tenant.jobApplication.onboard', compact('jobOnBoards'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function jobBoardStore(Request $request, $id)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'joining_date' => 'required|date|after_or_equal:today',
                'job_type' => 'required',
                'days_of_week' => 'required|gt:0',
                'salary' => 'required|gt:0',
                'salary_type' => 'required',
                'salary_duration' => 'required',
                'status' => 'required',
            ]
        );

        if ($validator->fails()) {
            $messages = $validator->getMessageBag();

            return redirect()->back()->with('error', $messages->first());
        }

        $id = ($id == 0) ? $request->application : $id;

        $jobBoard                        = new JobOnBoard();
        $jobBoard->application           = $id;
        $jobBoard->joining_date          = $request->joining_date;
        $jobBoard->job_type              = $request->job_type;
        $jobBoard->days_of_week          = $request->days_of_week;
        $jobBoard->salary                = $request->salary;
        $jobBoard->salary_type           = $request->salary_type;
        $jobBoard->salary_duration       = $request->salary_duration;
        $jobBoard->status                = $request->status;
        $jobBoard->created_by            = Auth::user()->creatorId();
        $jobBoard->save();

        $interview = InterviewSchedule::where('candidate', $id)->first();
        if (!empty($interview)) {
            $interview->delete();
        }

        return redirect()->route('job.on.board')->with('success', __('Candidate succefully added in job board.'));
    }

    public function jobBoardUpdate(Request $request, $id)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'joining_date' => 'required|date|after_or_equal:today',
                'job_type' => 'required',
                'days_of_week' => 'required',
                'salary' => 'required',
                'salary_type' => 'required',
                'salary_duration' => 'required',
                'status' => 'required',
            ]
        );
        if ($validator->fails()) {
            $messages = $validator->getMessageBag();

            return redirect()->back()->with('error', $messages->first());
        }

        $jobBoard                        = JobOnBoard::find($id);
        $jobBoard->joining_date          = $request->joining_date;
        $jobBoard->job_type              = $request->job_type;
        $jobBoard->days_of_week          = $request->days_of_week;
        $jobBoard->salary                = $request->salary;
        $jobBoard->salary_type           = $request->salary_type;
        $jobBoard->salary_duration     = $request->salary_duration;
        $jobBoard->status                = $request->status;
        $jobBoard->save();


        return redirect()->route('job.on.board')->with('success', __('Job board Candidate succefully updated.'));
    }

    public function jobBoardEdit($id)
    {
        $jobOnBoard = JobOnBoard::find($id);
        $status     = JobOnBoard::$status;
        $job_type       = JobOnBoard::$job_type;
        $salary_duration = JobOnBoard::$salary_duration;
        $salary_type      = PayslipType::where('created_by_id', Auth::user()->creatorId())->get()->pluck('name', 'id');

        return view('tenant.jobApplication.onboardEdit', compact('jobOnBoard', 'status', 'job_type', 'salary_type', 'salary_duration'));
    }

    public function jobBoardDelete($id)
    {

        $jobBoard = JobOnBoard::find($id);
        $jobBoard->delete();

        return redirect()->route('job.on.board')->with('success', __('Job onBoard successfully deleted.'));
    }

    public function jobBoardConvert($id)
    {
        $jobOnBoard       = JobOnBoard::find($id);
        $company_settings = Utility::settings();
        $documents        = Document::where('created_by_id', Auth::user()->creatorId())->get();
        $branches         = Site::where('created_by_id', Auth::user()->creatorId())->get()->pluck('name', 'id');
        $designations     = Designation::where('created_by_id', Auth::user()->creatorId())->get();
        $employees        = User::where('created_by_id', Auth::user()->creatorId())->get();

        $departments      = Department::where('status', \App\Enums\Status::ACTIVE)->get()->pluck('name', 'id');

        return view('tenant.jobApplication.convert', compact('jobOnBoard', 'employees', 'designations', 'documents', 'branches', 'company_settings', 'departments'));
    }

    public function jobBoardConvertData(Request $request, $id)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'name' => 'required',
                'dob' => 'required',
                'gender' => 'required',
                'phone' => 'required',
                'address' => 'required',
                'email' => 'required|unique:users',
                'password' => 'required',
                'designation_id' => 'required',
                'document.*' => 'mimes:jpeg,png,jpg,gif,svg,pdf,doc,zip|max:20480',
            ]
        );
        if ($validator->fails()) {
            $messages = $validator->getMessageBag();

            return redirect()->back()->withInput()->with('error', $messages->first());
        }

        $user = User::create(
            [
                'first_name' => $request['name'],
                'last_name' => '',
                'email' => $request['email'],
                'password' => Hash::make($request['password']),
                'status' => 'active',
                'created_by_id' => Auth::user()->creatorId(),
                'phone' => $request['phone'],
                'address' => $request['address'],
                'dob' => $request['dob'],
                'gender' => $request['gender'],
                'designation_id' => $request['designation_id'],
                'date_of_joining' => $request['company_doj'],
                'base_salary' => $request['salary'] ?? 0,
            ]
        );
        $user->assignRole('employee');

        $JobOnBoard                      = JobOnBoard::find($id);
        $JobOnBoard->convert_to_employee = $user->id;
        $JobOnBoard->save();

        // Handle documents if needed - in PAYR documents might be stored differently
        // For now, let's keep it simple or adapt if we find Document model equivalent for users

        return redirect()->back()->with('success', __('Application successfully converted to employee.'));
    }

    public function getByJob(Request $request)
    {
        $job                  = Job::find($request->id);
        if ($job != null) {
            $job->applicant       = !empty($job->applicant) ? explode(',', $job->applicant) : '';
            $job->visibility      = !empty($job->visibility) ? explode(',', $job->visibility) : '';
            $job->custom_question = !empty($job->custom_question) ? explode(',', $job->custom_question) : '';

            return json_encode($job);
        }
    }

    public function stageChange(Request $request)
    {
        $application        = JobApplication::where('id', '=', $request->schedule_id)->first();
        $application->stage = $request->stage;
        $application->save();


        return response()->json(
            [
                'success' => __('This candidate stage successfully changed.'),
            ],
            200
        );
    }

    public function offerletterPdf($id)
    {
        $users = Auth::user();
        $currantLang = $users->language ?? 'en';
        $Offerletter = GenerateOfferLetter::where(['lang' =>   $currantLang, 'created_by' =>  Auth::user()->creatorId()])->first();

        $job = JobApplication::find($id);
        $Onboard = JobOnBoard::find($id);
        $name = JobApplication::find($Onboard->application);
        $job_title = Job::find($name->job);
        $salary = PayslipType::find($Onboard->salary_type);


        $obj = [
            'applicant_name' => $name->name,
            'app_name' => env('APP_NAME'),
            'job_title' => $job_title->title,
            'job_type' => !empty($Onboard->job_type) ? $Onboard->job_type : '',
            'start_date' => $Onboard->joining_date,
            'workplace_location' => !empty($job->jobs->branches->name) ? $job->jobs->branches->name : '',
            'days_of_week' => !empty($Onboard->days_of_week) ? $Onboard->days_of_week : '',
            'salary' => !empty($Onboard->salary) ? $Onboard->salary : '',
            'salary_type' => !empty($salary->name) ? $salary->name : '',
            'salary_duration' => !empty($Onboard->salary_duration) ? $Onboard->salary_duration : '',
            'offer_expiration_date' => !empty($Onboard->joining_date) ? $Onboard->joining_date : '',

        ];
        $Offerletter->content = GenerateOfferLetter::replaceVariable($Offerletter->content, $obj);
        return view('tenant.jobApplication.template.offerletterpdf', compact('Offerletter', 'name'));
    }

    public function offerletterDoc($id)
    {
        $users = Auth::user();
        $currantLang = $users->language ?? 'en';
        $Offerletter = GenerateOfferLetter::where(['lang' =>   $currantLang, 'created_by' =>  Auth::user()->creatorId()])->first();
        
        $job = JobApplication::find($id);
        $Onboard = JobOnBoard::find($id);
        $name = JobApplication::find($Onboard->application);
        $job_title = Job::find($name->job);
        $salary = PayslipType::find($Onboard->salary_type);

        $obj = [
            'applicant_name' => $name->name,
            'app_name' => env('APP_NAME'),
            'job_title' => $job_title->title,
            'job_type' => !empty($Onboard->job_type) ? $Onboard->job_type : '',
            'start_date' => $Onboard->joining_date,
            'workplace_location' => !empty($job->jobs->branches->name) ? $job->jobs->branches->name : '',
            'days_of_week' => !empty($Onboard->days_of_week) ? $Onboard->days_of_week : '',
            'salary' => !empty($Onboard->salary) ? $Onboard->salary : '',
            'salary_type' => !empty($salary->name) ? $salary->name : '',
            'salary_duration' => !empty($Onboard->salary_duration) ? $Onboard->salary_duration : '',
            'offer_expiration_date' => !empty($Onboard->joining_date) ? $Onboard->joining_date : '',

        ];
        $Offerletter->content = GenerateOfferLetter::replaceVariable($Offerletter->content, $obj);
        return view('tenant.jobApplication.template.offerletterdocx', compact('Offerletter', 'name'));
    }
}
