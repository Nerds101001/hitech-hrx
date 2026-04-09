<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use App\Models\InterviewSchedule as LocalInterviewSchedule;
use App\Models\JobApplication;
use App\Models\JobStage;
use App\Models\User;
use App\Models\Utility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class InterviewScheduleController extends Controller
{

    public function index()
    {
        $schedules   = LocalInterviewSchedule::where('created_by', Auth::user()->creatorId())->get();
        $arrSchedule = [];
        $today_date = date('m');
        $current_month_event = LocalInterviewSchedule::select('id', 'candidate', 'date', 'employee', 'time', 'comment')->whereNotNull(['date'])->whereMonth('date', $today_date)->where('created_by', Auth::user()->creatorId())->get();
        foreach ($schedules as $key => $schedule) {
            $arr['id']     = $schedule->id;
            $arr['title']  = !empty($schedule->applications) ? !empty($schedule->applications->jobs) ? $schedule->applications->jobs->title : '' : '';
            $arr['start']  = $schedule->date;
            $arr['url']    = route('interview-schedule.show', $schedule->id);
            $arr['className'] = ' event-primary';
            $arrSchedule[] = $arr;
        }
        
        $arrSchedule = json_encode($arrSchedule);

        return view('tenant.interviewSchedule.index', compact('arrSchedule', 'schedules', 'current_month_event'));
    }

    public function create($candidate = 0)
    {

        $employees = User::where('created_by_id', Auth::user()->creatorId())->get()->pluck('name', 'id');

        $employees->prepend('--', '');

        $candidates = JobApplication::where('created_by', Auth::user()->creatorId())->get()->pluck('name', 'id');
        $candidates->prepend('--', '');

        return view('tenant.interviewSchedule.create', compact('employees', 'candidates', 'candidate'));
    }

    public function store(Request $request)
    {

        if (Auth::user()->can('Create Interview Schedule')) {
            $validator = Validator::make(
                $request->all(),
                [
                    'candidate' => 'required',
                    'employee' => 'required',
                    'date' => 'required|date|after_or_equal:today',
                    'time' => 'required',
                ]
            );

            if ($validator->fails()) {
                $messages = $validator->getMessageBag();

                return redirect()->back()->with('error', $messages->first());
            }

            $schedule             = new LocalInterviewSchedule();
            $schedule->candidate  = $request->candidate;
            $schedule->employee   = $request->employee;
            $schedule->date       = $request->date;
            $schedule->time       = $request->time;
            $schedule->comment    = $request->comment;
            $schedule->created_by = Auth::user()->creatorId();
            $schedule->save();

            return redirect()->back()->with('success', __('Interview schedule successfully created.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function show(LocalInterviewSchedule $interviewSchedule)
    {
        $stages = JobStage::where('created_by', Auth::user()->creatorId())->get();
        return view('tenant.interviewSchedule.show', compact('interviewSchedule', 'stages'));
    }

    public function edit(LocalInterviewSchedule $interviewSchedule)
    {
        $employees = User::where('created_by_id', Auth::user()->creatorId())->get()->pluck('name', 'id');

        $employees->prepend('--', '');

        $candidates = JobApplication::where('created_by', Auth::user()->creatorId())->get()->pluck('name', 'id');
        $candidates->prepend('--', '');

        return view('tenant.interviewSchedule.edit', compact('employees', 'candidates', 'interviewSchedule'));
    }

    public function update(Request $request, LocalInterviewSchedule $interviewSchedule)
    {
        if (Auth::user()->can('Edit Interview Schedule')) {
            $validator = Validator::make(
                $request->all(),
                [
                    'candidate' => 'required',
                    'employee' => 'required',
                    'date' => 'required|date|after_or_equal:today',
                    'time' => 'required',
                ]
            );

            if ($validator->fails()) {
                $messages = $validator->getMessageBag();

                return redirect()->back()->with('error', $messages->first());
            }


            $interviewSchedule->candidate = $request->candidate;
            $interviewSchedule->employee  = $request->employee;
            $interviewSchedule->date      = $request->date;
            $interviewSchedule->time      = $request->time;
            $interviewSchedule->comment   = $request->comment;
            $interviewSchedule->save();

            return redirect()->back()->with('success', __('Interview schedule successfully updated.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function destroy(LocalInterviewSchedule $interviewSchedule)
    {
        if (Auth::user()->can('Delete Interview Schedule')) {
            $interviewSchedule->delete();

            return redirect()->back()->with('success', __('Interview schedule successfully deleted.'));
        } else {
             return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function get_interview_schedule_data(Request $request)
    {
        $arrayJson = [];
        $data = LocalInterviewSchedule::where('created_by', Auth::user()->creatorId())->get();

        foreach ($data as $val) {
            $end_date = date_create($val->end_date); // Note: end_date might be missing in migration, check
            // If end_date is missing, use date
            $base_date = $val->date ?? date('Y-m-d');
            $end_date = date_create($base_date);
            date_add($end_date, date_interval_create_from_date_string("1 days"));
            $arrayJson[] = [
                "id" => $val->id,
                "title" => !empty($val->applications) ? (!empty($val->applications->jobs) ? $val->applications->jobs->title : '') : '',
                "start" => $val->date,
                "end" => date_format($end_date, "Y-m-d H:i:s"),
                "className" => 'event-primary',
                "textColor" => '#FFF',
                "allDay" => true,
                "url" => route('interview-schedule.show', $val->id),
            ];
        }

        return $arrayJson;
    }
}
