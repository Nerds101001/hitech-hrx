@extends('layouts/layoutMaster')

@section('title', 'Onboarding Review Center')

@section('vendor-style')
    <!-- Tailwind CSS with Forms and Typography plugins -->
    <script src="https://cdn.tailwindcss.com?plugins=forms,typography,aspect-ratio"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&amp;display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <style type="text/tailwindcss">
        :root {
            --primary-teal: #006D77;
            --deep-teal: #004d54;
            --sidebar-bg: #00353a;
            --bg-light: #F8FAFC;
        }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        .status-badge {
            @apply px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider;
        }
        /* Modal Styles */
        .onboarding-modal-backdrop { @apply fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[60]; }
        .onboarding-modal-content { @apply fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 bg-white rounded-2xl shadow-2xl z-[70] w-full max-w-lg p-0 overflow-hidden; }
        
        /* Ensure tailwind doesn't clash too much with bootstrap */
        .tailwind-scope {
            font-family: 'Inter', sans-serif;
        }
    </style>
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        primary: "#006D77",
                        "deep-teal": "#004d54",
                        "sidebar-bg": "#00353a",
                    }
                },
            },
        };
    </script>
@endsection

@section('page-style')
<style>
    /* Custom Scrollbar */
    .onboarding-scrollbar::-webkit-scrollbar { width: 6px; }
    .onboarding-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .onboarding-scrollbar::-webkit-scrollbar-thumb { background: #CBD5E1; border-radius: 10px; }
    .onboarding-scrollbar::-webkit-scrollbar-thumb:hover { background: #94A3B8; }
    
    /* Layout Overrides for layoutMaster */
    .content-wrapper { background-color: #F8FAFC !important; }

    /* Onboarding Form Styles for Audit Modal */
    :root {
        --primary-teal: #006D77;
        --deep-teal: #004d54;
        --bg-light: #F8FAFC;
    }

    .hitech-stepper-wrapper {
        max-width: 100%;
        margin: 1.5rem 0;
    }

    .step-circle {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.85rem;
        font-weight: 700;
        border: 2px solid #E2E8F0;
        background: white;
        color: #94A3B8;
        transition: all 0.3s ease;
    }

    .step-custom.active .step-circle {
        border-color: var(--primary-teal);
        background: var(--primary-teal);
        color: white;
        box-shadow: 0 8px 20px rgba(0, 109, 119, 0.25);
    }

    .step-label {
        font-size: 10px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        margin-top: 0.5rem;
        color: #94A3B8;
    }

    .step-custom.active .step-label {
        color: var(--deep-teal);
    }

    .stepper-line {
        height: 2px;
        background: #E2E8F0;
        flex: 1;
        margin: 0 0.5rem;
        transform: translateY(-12px);
    }

    .onboarding-card {
        background: white;
        border-radius: 20px;
        border: 1px solid #E2E8F0;
        overflow: hidden;
    }

    .card-header-hitech {
        background: var(--deep-teal);
        padding: 1.5rem 2rem;
        color: white;
    }

    .card-header-hitech h2 {
        font-weight: 800;
        font-size: 1.25rem;
        margin: 0;
        color: white;
    }

    .card-header-hitech p {
        opacity: 0.8;
        margin: 0.25rem 0 0;
        font-size: 0.85rem;
    }

    .card-body-hitech {
        padding: 2rem;
    }



    .section-title {
        font-size: 1rem;
        font-weight: 700;
        color: var(--deep-teal);
        margin-top: 1.5rem;
        margin-bottom: 1rem;
        padding-bottom: 0.5rem;
        border-bottom: 1px solid #E2E8F0;
    }

    .hitech-modal-footer {
        background: white;
        border-top: 1px solid #E2E8F0;
        padding: 1rem 2rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .btn-prev-hitech {
        background: white;
        border: 1px solid #E2E8F0;
        color: #64748B;
        padding: 0.6rem 1.2rem;
        border-radius: 10px;
        font-weight: 700;
        font-size: 0.8rem;
        display: flex;
        align-items: center;
        gap: 0.4rem;
    }

    .btn-next-hitech {
        background: var(--deep-teal);
        color: white !important;
        border: none;
        padding: 0.6rem 1.5rem;
        border-radius: 10px;
        font-weight: 700;
        font-size: 0.8rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
</style>
@endsection

@section('content')
<div class="tailwind-scope text-slate-800">
    {{-- Main Header inside section --}}
    <div class="flex flex-col md:flex-row md:items-end justify-between mb-10 gap-4 mt-4 px-4">
        <div class="animate__animated animate__fadeIn">
            <h1 class="text-3xl font-black text-slate-900 tracking-tight">Onboarding Review Center</h1>
            <p class="text-slate-500 text-sm mt-1 font-medium">Coordinate, audit, and finalize candidate integration workflows.</p>
        </div>
        <div class="flex items-center gap-4">
            <button onclick="openOnboardingModal()" class="bg-primary hover:bg-deep-teal text-white px-5 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2 transition-all shadow-lg shadow-primary/20 active:scale-95">
                <span class="material-symbols-outlined text-lg">person_add</span>
                New Onboarding
            </button>
            <div class="bg-amber-50 border border-amber-200 px-5 py-2.5 rounded-2xl flex items-center gap-3 shadow-sm">
                <div class="w-8 h-8 bg-amber-500 text-white rounded-lg flex items-center justify-center">
                    <span class="material-symbols-outlined text-lg">pending_actions</span>
                </div>
                <div>
                    <p class="text-[10px] font-bold text-amber-600 uppercase tracking-widest leading-none mb-1">Attention Required</p>
                    <p class="text-sm font-black text-amber-900 leading-none">{{ $pendingCount }} Pending Reviews</p>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-4 gap-10 items-start px-4">
        {{-- Left Pane: Pending List --}}
        <div class="xl:col-span-3 space-y-8">
            <div class="bg-white rounded-3xl shadow-xl shadow-slate-200/60 border border-slate-200 overflow-hidden">
                <div class="p-8 border-b border-slate-100 flex flex-col md:flex-row justify-between items-center bg-slate-50/30 gap-6">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 bg-primary/10 rounded-xl flex items-center justify-center">
                            <span class="material-symbols-outlined text-primary">analytics</span>
                        </div>
                        <div>
                            <h3 class="font-black text-slate-900 uppercase tracking-widest text-xs">Submission Pipeline</h3>
                            <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mt-0.5">Live Candidate Data</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 w-full md:w-auto">
                        <div class="relative flex-1 md:flex-none">
                            <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg">search</span>
                            <input class="pl-12 pr-6 py-3 text-sm rounded-2xl border-slate-200 bg-white w-full md:w-72 focus:ring-primary/20 focus:border-primary transition-all placeholder:text-slate-400 text-slate-900 font-medium" placeholder="Filter by candidate or ID..." type="text"/>
                        </div>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left table-fixed">
                        <thead>
                            <tr class="bg-slate-50/50">
                                <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] w-1/3">Candidate Detail</th>
                                <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">Submitted</th>
                                <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">Department</th>
                                <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">Status</th>
                                <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($onboardingUsers->where('status', \App\Enums\UserAccountStatus::ONBOARDING_SUBMITTED) as $oUser)
                                <tr class="group hover:bg-slate-50/80 transition-all cursor-pointer {{ $selectedUser && $selectedUser->id == $oUser->id ? 'bg-primary/[0.03] ring-1 ring-inset ring-primary/10' : '' }}" onclick="window.location.href='{{ route('employees.onboarding.review_center', ['user_id' => $oUser->id]) }}'">
                                    <td class="px-8 py-6">
                                        <div class="flex items-center gap-4">
                                            <div class="relative">
                                                <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-slate-100 to-slate-200 flex items-center justify-center text-slate-900 font-black text-base shadow-inner border border-white">
                                                    {{ $oUser->getInitials() }}
                                                </div>
                                                <div class="absolute -bottom-1 -right-1 w-4 h-4 bg-amber-500 border-2 border-white rounded-full"></div>
                                            </div>
                                            <div class="min-w-0">
                                                <p class="text-sm font-black text-slate-900 truncate">{{ $oUser->getFullName() }}</p>
                                                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mt-0.5">{{ $oUser->roles->first()->display_name ?? 'N/A' }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-8 py-6">
                                        <div class="flex flex-col">
                                            <span class="text-sm text-slate-600 font-bold">{{ $oUser->onboarding_completed_at ? \Carbon\Carbon::parse($oUser->onboarding_completed_at)->format('d M, Y') : 'N/A' }}</span>
                                            <span class="text-[10px] text-slate-400 font-medium uppercase tracking-tighter">{{ $oUser->onboarding_completed_at ? \Carbon\Carbon::parse($oUser->onboarding_completed_at)->format('H:i A') : '' }}</span>
                                        </div>
                                    </td>
                                    <td class="px-8 py-6">
                                        <div class="inline-flex items-center gap-2 px-3 py-1 bg-slate-100 rounded-lg text-slate-600 text-[10px] font-black uppercase tracking-wider">
                                            <span class="w-1.5 h-1.5 bg-slate-400 rounded-full"></span>
                                            {{ $oUser->team->name ?? 'Unmanaged' }}
                                        </div>
                                    </td>
                                    <td class="px-8 py-6">
                                        <span class="px-3 py-1.5 rounded-xl text-[9px] font-black uppercase tracking-widest bg-amber-100 text-amber-700 ring-1 ring-inset ring-amber-500/20">Awaiting HR Audit</span>
                                    </td>
                                    <td class="px-8 py-6 text-right">
                                        <div class="flex items-center justify-end gap-3 opacity-0 group-hover:opacity-100 transition-opacity">
                                            <a href="{{ route('employees.onboarding.review_center', ['user_id' => $oUser->id]) }}" class="px-4 py-2 text-[10px] font-black uppercase tracking-[0.2em] text-primary bg-primary/5 hover:bg-primary hover:text-white rounded-xl transition-all border border-primary/20">Audit File</a>
                                            <form method="POST" action="{{ route('employees.onboarding.approve', $oUser->id) }}">
                                                @csrf
                                                <button type="submit" class="w-9 h-9 flex items-center justify-center text-green-600 hover:bg-green-50 rounded-xl transition-colors border border-green-200" title="Instant Approval">
                                                    <span class="material-symbols-outlined text-xl">verified</span>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-8 py-20 text-center">
                                        <div class="flex flex-col items-center">
                                            <div class="w-20 h-20 bg-slate-100 rounded-full flex items-center justify-center mb-4">
                                                <span class="material-symbols-outlined text-4xl text-slate-300">work_outline</span>
                                            </div>
                                            <p class="text-slate-500 font-bold uppercase tracking-widest text-xs">All caught up! No pending reviews found.</p>
                                            <p class="text-slate-400 text-[10px] mt-1">Review recently approved candidates in the side panel.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Detail View Pane --}}
            @if($selectedUser)
            <div class="bg-white rounded-3xl shadow-2xl shadow-slate-200/40 border border-slate-200 overflow-hidden animate__animated animate__fadeInUp">
                <div class="px-8 py-6 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 bg-primary text-white rounded-xl flex items-center justify-center shadow-lg shadow-primary/20">
                            <span class="material-symbols-outlined text-lg">fact_check</span>
                        </div>
                        <div>
                            <h3 class="font-black text-slate-900 uppercase tracking-widest text-xs">Audit File: {{ $selectedUser->getFullName() }}</h3>
                            <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mt-0.5">Reference ID: #EMP-{{ $selectedUser->id }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-4">
                        <span class="text-[10px] text-slate-400 font-black uppercase tracking-widest">Global Status:</span>
                        <span class="bg-primary/10 text-primary text-[9px] font-black px-3 py-1 rounded-full uppercase tracking-widest">Active Review</span>
                    </div>
                </div>
                <div class="p-10">
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-12">
                        {{-- Docs --}}
                        <div class="space-y-6">
                            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                                <h4 class="text-[11px] font-black text-slate-900 uppercase tracking-widest flex items-center gap-2">
                                    <span class="material-symbols-outlined text-slate-400 text-lg">folder_open</span>
                                    Documentation
                                </h4>
                                <span class="bg-slate-100 text-slate-500 text-[9px] px-2 py-0.5 rounded-md font-bold uppercase tracking-wider">Vault</span>
                            </div>
                            <div class="space-y-3 onboarding-scrollbar max-h-[400px] overflow-y-auto">
                                @php
                                    $onboardingFolder = \Constants::BaseFolderOnboardingDocuments . $selectedUser->id;
                                    $files = \Illuminate\Support\Facades\Storage::disk('public')->files($onboardingFolder);
                                @endphp
                                @foreach($files as $file)
                                <div class="group flex items-center justify-between p-4 bg-slate-50 hover:bg-white rounded-2xl border border-slate-100 hover:border-primary/30 transition-all hover:shadow-lg hover:shadow-slate-200/50">
                                    <div class="flex items-center gap-4">
                                        <div class="w-10 h-10 bg-white rounded-xl flex items-center justify-center text-primary group-hover:scale-110 transition-transform">
                                            <span class="material-symbols-outlined">attachment</span>
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-xs font-bold text-slate-900 truncate">{{ basename($file) }}</p>
                                            <p class="text-[9px] text-slate-400 uppercase font-black tracking-widest">Digital Asset</p>
                                        </div>
                                    </div>
                                    <a href="{{ \App\Helpers\FileSecurityHelper::generateSecureUrl($file) }}" target="_blank" class="w-9 h-9 bg-white flex items-center justify-center rounded-xl text-primary border border-slate-200 hover:border-primary hover:bg-primary hover:text-white transition-all shadow-sm">
                                        <span class="material-symbols-outlined text-lg">open_in_new</span>
                                    </a>
                                </div>
                                @endforeach
                                @if(count($files) == 0)
                                    <div class="p-8 text-center bg-slate-50 rounded-2xl border border-dashed border-slate-200">
                                        <p class="text-[10px] text-slate-400 font-black uppercase tracking-[0.1em]">No supporting files uploaded</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                        {{-- Info --}}
                        <div class="space-y-6">
                            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                                <h4 class="text-[11px] font-black text-slate-900 uppercase tracking-widest flex items-center gap-2">
                                    <span class="material-symbols-outlined text-slate-400 text-lg">contact_page</span>
                                    Core Intelligence
                                </h4>
                            </div>
                            <div class="space-y-6">
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="p-4 bg-slate-50/50 rounded-xl">
                                        <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Legal Name</p>
                                        <p class="text-xs font-bold text-slate-900">{{ $selectedUser->getFullName() }}</p>
                                    </div>
                                    <div class="p-4 bg-slate-50/50 rounded-xl">
                                        <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Corporate ID</p>
                                        <p class="text-xs font-bold text-slate-900 truncate">HITECH-{{ $selectedUser->id }}</p>
                                    </div>
                                </div>
                                <div class="p-4 bg-slate-50/50 rounded-xl">
                                    <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Residential Coordinates</p>
                                    <p class="text-xs font-bold text-slate-900 leading-relaxed">{{ $selectedUser->address ?? 'No physical address reported' }}</p>
                                </div>
                                <div class="p-4 bg-slate-50/50 rounded-xl">
                                    <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Communication Channel</p>
                                    <div class="flex items-center gap-2">
                                        <span class="material-symbols-outlined text-xs text-primary">mail</span>
                                        <p class="text-xs font-bold text-slate-900 truncate">{{ $selectedUser->email }}</p>
                                    </div>
                                    <div class="flex items-center gap-2 mt-1">
                                        <span class="material-symbols-outlined text-xs text-primary">phone_iphone</span>
                                        <p class="text-xs font-bold text-slate-900">{{ $selectedUser->phone }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        {{-- Control --}}
                        <div class="space-y-6">
                            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                                <h4 class="text-[11px] font-black text-slate-900 uppercase tracking-widest flex items-center gap-2">
                                    <span class="material-symbols-outlined text-slate-400 text-lg">lock_open</span>
                                    Executive Control
                                </h4>
                            </div>
                            <div class="flex flex-col gap-4">
                                <form method="POST" action="{{ route('employees.onboarding.approve', $selectedUser->id) }}">
                                    @csrf
                                    <button type="submit" class="w-full bg-primary text-white py-4 rounded-2xl text-xs font-black uppercase tracking-widest shadow-xl shadow-primary/20 hover:bg-deep-teal transition-all active:scale-95 flex items-center justify-center gap-3">
                                        <span class="material-symbols-outlined text-xl">new_releases</span>
                                        Grant Dashboard Access
                                    </button>
                                </form>
                                
                                <button onclick="document.getElementById('resubmit-box').classList.toggle('hidden')" class="w-full bg-white border-2 border-slate-100 text-slate-500 py-4 rounded-2xl text-xs font-black uppercase tracking-widest hover:border-red-500 hover:text-red-500 transition-all flex items-center justify-center gap-3">
                                    <span class="material-symbols-outlined text-xl">published_with_changes</span>
                                    Challenge Submission
                                </button>
                                
                                <div id="resubmit-box" class="hidden mt-2 p-6 bg-red-50 rounded-2xl border border-red-100 shadow-inner">
                                    <form method="POST" action="{{ route('employees.onboarding.resubmit', $selectedUser->id) }}">
                                        @csrf
                                        <p class="text-[10px] font-black text-red-600 uppercase tracking-widest mb-3 italic">Identify Required Corrections:</p>
                                        
                                        <div class="space-y-2 mb-4">
                                            <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2">Unlock Specific Sections:</p>
                                            <div class="grid grid-cols-2 gap-3">
                                                <label class="flex items-center gap-2 cursor-pointer group">
                                                    <input type="checkbox" name="sections[]" value="personal" class="rounded border-slate-300 text-red-600 focus:ring-red-500" checked>
                                                    <span class="text-[10px] font-bold text-slate-600 group-hover:text-red-600 transition-colors">Personal Info</span>
                                                </label>
                                                <label class="flex items-center gap-2 cursor-pointer group">
                                                    <input type="checkbox" name="sections[]" value="contact" class="rounded border-slate-300 text-red-600 focus:ring-red-500">
                                                    <span class="text-[10px] font-bold text-slate-600 group-hover:text-red-600 transition-colors">Contact/Address</span>
                                                </label>
                                                <label class="flex items-center gap-2 cursor-pointer group">
                                                    <input type="checkbox" name="sections[]" value="banking" class="rounded border-slate-300 text-red-600 focus:ring-red-500">
                                                    <span class="text-[10px] font-bold text-slate-600 group-hover:text-red-600 transition-colors">Banking Details</span>
                                                </label>
                                                <label class="flex items-center gap-2 cursor-pointer group">
                                                    <input type="checkbox" name="sections[]" value="documents" class="rounded border-slate-300 text-red-600 focus:ring-red-500">
                                                    <span class="text-[10px] font-bold text-slate-600 group-hover:text-red-600 transition-colors">Identity Docs</span>
                                                </label>
                                            </div>
                                        </div>

                                        <textarea name="notes" placeholder="Detailed audit notes for candidate..." class="w-full px-4 py-3 text-sm border-red-200 rounded-xl mb-4 focus:ring-red-500 focus:border-red-500 bg-white placeholder:text-red-300 font-medium" rows="3" required></textarea>
                                        <button type="submit" class="w-full bg-red-600 text-white py-3 rounded-xl text-[10px] font-black uppercase tracking-[0.2em] shadow-lg shadow-red-200 hover:bg-red-700 transition-all">Relay Instruction</button>
                                    </form>
                                </div>
                            </div>
                            <div class="p-6 bg-slate-50 rounded-2xl border border-slate-100 border-dashed">
                                <div class="flex items-start gap-4">
                                    <span class="material-symbols-outlined text-primary text-lg mt-0.5">info</span>
                                    <p class="text-[10px] text-slate-500 font-bold leading-relaxed uppercase tracking-widest group">
                                        Approving this candidate will transition their system status to <span class="text-primary underline">ACTIVE</span> and provision all core platform modules.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @else
                <div class="bg-white/40 backdrop-blur-sm rounded-[3rem] p-24 text-center border-2 border-dashed border-slate-200/60 group hover:border-primary/20 transition-all flex flex-col items-center justify-center">
                    <div class="relative mb-8">
                        <div class="w-32 h-32 bg-white rounded-[2rem] shadow-2xl shadow-slate-200 flex items-center justify-center text-slate-200 group-hover:text-primary/20 transition-colors">
                            <span class="material-symbols-outlined text-7xl font-light">person_search</span>
                        </div>
                        <div class="absolute -top-4 -right-4 w-12 h-12 bg-primary/5 rounded-full animate-ping"></div>
                    </div>
                    <h3 class="text-xl font-black text-slate-900 uppercase tracking-widest mb-3">Audit Inspector Idle</h3>
                    <p class="text-slate-500 max-w-xs text-sm font-medium leading-relaxed">Please select a pending candidate from the submission pipeline above to initialize deep audit proceedings.</p>
                </div>
            @endif
        </div>

        {{-- Right Pane: Recently Approved --}}
        <div class="xl:col-span-1 h-full">
            <div class="bg-white rounded-[2.5rem] shadow-2xl shadow-slate-200/60 border border-slate-200 overflow-hidden sticky top-8">
                <div class="p-8 border-b border-slate-100 bg-slate-50/50">
                    <h3 class="font-black text-slate-900 uppercase tracking-[0.2em] text-[10px] flex items-center gap-3">
                        <div class="w-2 h-6 bg-green-500 rounded-full"></div>
                        Recent Success
                    </h3>
                </div>
                <div class="divide-y divide-slate-50 max-h-[500px] overflow-y-auto onboarding-scrollbar">
                    @forelse($recentlyApproved as $approved)
                    <div class="p-6 flex items-center gap-5 group hover:bg-green-50/10 transition-all border-l-4 border-transparent hover:border-green-500">
                        <div class="relative">
                            <div class="w-12 h-12 rounded-[1.25rem] bg-gradient-to-tr from-slate-50 to-white flex items-center justify-center border border-slate-100 shadow-sm group-hover:scale-105 transition-transform overflow-hidden font-black text-slate-400">
                                @php $profilePic = $approved->getProfilePicture(); @endphp
                                @if($profilePic)
                                    <img src="{{ $profilePic }}" class="w-full h-full object-cover">
                                @else
                                    {{ $approved->getInitials() }}
                                @endif
                            </div>
                            <div class="absolute -bottom-1 -right-1 w-5 h-5 bg-green-500 border-4 border-white rounded-full flex items-center justify-center">
                                <span class="material-symbols-outlined text-[10px] text-white font-black">done</span>
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-black text-slate-900 truncate tracking-tight">{{ $approved->getFullName() }}</p>
                            <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mt-0.5 truncate">{{ $approved->team->name ?? 'Core System' }} • <span class="text-green-600/60 font-black">{{ \Carbon\Carbon::parse($approved->onboarding_completed_at)->diffForHumans(null, true) }}</span></p>
                        </div>
                    </div>
                    @empty
                    <div class="p-16 text-center">
                        <span class="material-symbols-outlined text-slate-100 text-6xl mb-4 block">history</span>
                        <p class="text-[10px] text-slate-300 font-bold uppercase tracking-[0.25em]">Registry Empty</p>
                    </div>
                    @endforelse
                </div>
                <div class="p-8 border-t border-slate-50">
                    <a href="{{ route('employees.index') }}" class="w-full flex items-center justify-center gap-3 bg-slate-50 hover:bg-slate-100 text-slate-500 py-4 rounded-2xl text-[10px] font-black uppercase tracking-[0.25em] transition-all border border-slate-100">
                        Global Registry
                        <span class="material-symbols-outlined text-xs">arrow_forward_ios</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Invitation Modal (Re-using standard design) --}}
<div id="onboardingInviteModal" class="hidden">
    <div class="onboarding-modal-backdrop" onclick="closeOnboardingModal()"></div>
    <div class="onboarding-modal-content animate__animated animate__zoomIn animate__faster">
        <div class="p-8 pb-4 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-primary/10 rounded-2xl flex items-center justify-center text-primary">
                    <span class="material-symbols-outlined text-2xl">person_add_alt_1</span>
                </div>
                <div>
                    <h2 class="text-xl font-black text-slate-900 tracking-tight">Candidate Deployment</h2>
                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mt-0.5">Initialize Onboarding Invitation</p>
                </div>
            </div>
            <button onclick="closeOnboardingModal()" class="w-10 h-10 flex items-center justify-center text-slate-400 hover:bg-slate-100 rounded-xl transition-colors">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <div class="p-8 pt-6">
            <form id="onboardingInviteForm" action="{{ route('employees.initiateOnboarding') }}" method="POST" class="space-y-6">
                @csrf
                <div class="grid grid-cols-2 gap-6">
                    <div class="space-y-2">
                        <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Given Name</label>
                        <input type="text" name="firstName" class="w-full px-4 py-3 bg-slate-50 border-slate-200 rounded-2xl focus:ring-primary/20 focus:border-primary font-bold text-sm" placeholder="John" required>
                    </div>
                    <div class="space-y-2">
                        <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Family Name</label>
                        <input type="text" name="lastName" class="w-full px-4 py-3 bg-slate-50 border-slate-200 rounded-2xl focus:ring-primary/20 focus:border-primary font-bold text-sm" placeholder="Doe" required>
                    </div>
                </div>
                <div class="space-y-2">
                    <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Verified Digital ID (Email)</label>
                    <input type="email" name="email" class="w-full px-4 py-3 bg-slate-50 border-slate-200 rounded-2xl focus:ring-primary/20 focus:border-primary font-bold text-sm" placeholder="candidate@provider.com" required>
                </div>
                <div class="space-y-2">
                    <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Telecommunications Handle</label>
                    <input type="text" name="phone" class="w-full px-4 py-3 bg-slate-50 border-slate-200 rounded-2xl focus:ring-primary/20 focus:border-primary font-bold text-sm" placeholder="Mobile Number" required maxlength="10">
                </div>
                <div class="grid grid-cols-2 gap-6">
                    <div class="space-y-2">
                        <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Operational Role</label>
                        <select name="role" class="w-full px-4 py-3 bg-slate-50 border-slate-200 rounded-2xl focus:ring-primary/20 focus:border-primary font-bold text-sm" required>
                            <option value="">Select Protocol</option>
                            @foreach($roles as $role)
                                <option value="{{ $role->name }}">{{ $role->display_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="space-y-2">
                        <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Assigned Sector (Department)</label>
                        <select name="teamId" class="w-full px-4 py-3 bg-slate-50 border-slate-200 rounded-2xl focus:ring-primary/20 focus:border-primary font-bold text-sm" required>
                            <option value="">Select Division</option>
                            @foreach($teams as $team)
                                <option value="{{ $team->id }}">{{ $team->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-6">
                    <div class="space-y-2">
                        <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Designation</label>
                        <select name="designationId" class="w-full px-4 py-3 bg-slate-50 border-slate-200 rounded-2xl focus:ring-primary/20 focus:border-primary font-bold text-sm" required>
                            <option value="">Select Rank</option>
                            @foreach($designations as $designation)
                                <option value="{{ $designation->id }}">{{ $designation->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="space-y-2">
                        <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Reporting To (Senior)</label>
                        <select name="reportingToId" class="w-full px-4 py-3 bg-slate-50 border-slate-200 rounded-2xl focus:ring-primary/20 focus:border-primary font-bold text-sm" required>
                            <option value="">Select Superior</option>
                            @foreach($managers as $manager)
                                <option value="{{ $manager->id }}">{{ $manager->getFullName() }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-6">
                    <div class="space-y-2">
                        <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Unit Manager / Site</label>
                        <select name="siteId" class="w-full px-4 py-3 bg-slate-50 border-slate-200 rounded-2xl focus:ring-primary/20 focus:border-primary font-bold text-sm">
                            <option value="">Select Unit (Optional)</option>
                            @php $sites = \App\Models\Site::where('status', 'active')->get(); @endphp
                            @foreach($sites as $site)
                                <option value="{{ $site->id }}">{{ $site->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="space-y-2">
                        <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Deployment Date (DOJ)</label>
                        <input type="date" name="doj" class="w-full px-4 py-3 bg-slate-50 border-slate-200 rounded-2xl focus:ring-primary/20 focus:border-primary font-bold text-sm" required>
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Base Compensation (Salary)</label>
                    <input type="number" name="baseSalary" class="w-full px-4 py-3 bg-slate-50 border-slate-200 rounded-2xl focus:ring-primary/20 focus:border-primary font-bold text-sm" placeholder="Monthly Base" required>
                </div>
                <div class="pt-6 border-t border-slate-100 flex gap-4">
                    <button type="button" onclick="closeOnboardingModal()" class="flex-1 py-4 text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] hover:text-slate-600 transition-colors">Abort Procedure</button>
                    <button type="submit" class="flex-[2] bg-primary text-white py-4 rounded-2xl text-[10px] font-black uppercase tracking-[0.2em] shadow-xl shadow-primary/20 hover:bg-deep-teal transition-all">Relay Invitation</button>
                </div>
            </form>
        </div>
    </div>
</div>

@if($selectedUser)
<div class="modal fade" id="auditDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen modal-dialog-scrollable">
        <div class="modal-content border-0">
            <div class="modal-header border-bottom bg-white" style="height: 70px;">
                <div class="d-flex align-items-center gap-3">
                    <div class="brand-icon-box" style="width: 32px; height: 32px; background: var(--primary-teal); border-radius: 6px; display: flex; align-items: center; justify-content: center; color: white;">
                        <i class="bx bx-layer"></i>
                    </div>
                    <h5 class="modal-title fw-black uppercase tracking-widest text-xs mb-0">Audit Proceeding: {{ $selectedUser->getFullName() }}</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body p-0 bg-light">
                <div class="hitech-stepper-wrapper px-5 py-4 bg-white border-bottom">
                    <div class="d-flex align-items-center justify-content-between mx-auto" style="max-width: 800px;">
                        <div class="step-custom active d-flex flex-column align-items-center" data-step="1">
                            <div class="step-circle step-circle-audit">1</div>
                            <span class="step-label">Personal</span>
                        </div>
                        <div class="stepper-line stepper-line-audit"></div>
                        <div class="step-custom d-flex flex-column align-items-center" data-step="2">
                            <div class="step-circle step-circle-audit">2</div>
                            <span class="step-label">Contact</span>
                        </div>
                        <div class="stepper-line stepper-line-audit"></div>
                        <div class="step-custom d-flex flex-column align-items-center" data-step="3">
                            <div class="step-circle step-circle-audit">3</div>
                            <span class="step-label">Banking</span>
                        </div>
                        <div class="stepper-line stepper-line-audit"></div>
                        <div class="step-custom d-flex flex-column align-items-center" data-step="4">
                            <div class="step-circle step-circle-audit">4</div>
                            <span class="step-label">Docs</span>
                        </div>
                        <div class="stepper-line stepper-line-audit"></div>
                        <div class="step-custom d-flex flex-column align-items-center" data-step="5">
                            <div class="step-circle step-circle-audit">5</div>
                            <span class="step-label">Review</span>
                        </div>
                    </div>
                </div>

                <div class="container py-5" style="max-width: 900px;">
                    {{-- Step 1: Personal --}}
                    <div id="audit-step-1" class="audit-content onboarding-card animate__animated animate__fadeIn">
                        <div class="card-header-hitech">
                            <h2>Personal Information</h2>
                            <p>Verify legal identity and family registry details.</p>
                        </div>
                        <div class="card-body-hitech">
                            <h4 class="section-title">Core Identity</h4>
                            <div class="row g-4 mb-4">
                                <div class="col-md-6">
                                    <label class="hitech-label">First Name</label>
                                    <div class="hitech-input-readonly">{{ $selectedUser->first_name }}</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="hitech-label">Last Name</label>
                                    <div class="hitech-input-readonly">{{ $selectedUser->last_name }}</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="hitech-label">Date of Birth</label>
                                    <div class="hitech-input-readonly">{{ $selectedUser->dob ? \Carbon\Carbon::parse($selectedUser->dob)->format('d M, Y') : 'N/A' }}</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="hitech-label">Gender</label>
                                    <div class="hitech-input-readonly">{{ ucfirst($selectedUser->gender) }}</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="hitech-label">Blood Group</label>
                                    <div class="hitech-input-readonly">{{ $selectedUser->blood_group ?: 'N/A' }}</div>
                                </div>
                                <div class="col-md-12">
                                    <label class="hitech-label">Highest Qualification</label>
                                    <div class="hitech-input-readonly">{{ ucfirst($selectedUser->highest_qualification) }}</div>
                                </div>
                            </div>
                            <h4 class="section-title">Family Details</h4>
                            <div class="row g-4 mb-4">
                                <div class="col-md-6">
                                    <label class="hitech-label">Father's Name</label>
                                    <div class="hitech-input-readonly">{{ $selectedUser->father_name }}</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="hitech-label">Mother's Name</label>
                                    <div class="hitech-input-readonly">{{ $selectedUser->mother_name }}</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="hitech-label">Marital Status</label>
                                    <div class="hitech-input-readonly">{{ ucfirst($selectedUser->marital_status) }}</div>
                                </div>
                                @if($selectedUser->marital_status == 'married')
                                <div class="col-md-6">
                                    <label class="hitech-label">Spouse Name</label>
                                    <div class="hitech-input-readonly">{{ $selectedUser->spouse_name ?: 'N/A' }}</div>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Step 2: Contact --}}
                    <div id="audit-step-2" class="audit-content onboarding-card animate__animated animate__fadeIn d-none">
                        <div class="card-header-hitech">
                            <h2>Contact & Address</h2>
                            <p>Verify residential coordinates and emergency registry.</p>
                        </div>
                        <div class="card-body-hitech">
                            <h4 class="section-title">Communication</h4>
                            <div class="row g-4 mb-4">
                                <div class="col-md-6">
                                    <label class="hitech-label">Email Address</label>
                                    <div class="hitech-input-readonly">{{ $selectedUser->email }}</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="hitech-label">Primary Phone</label>
                                    <div class="hitech-input-readonly">{{ $selectedUser->phone }}</div>
                                </div>
                            </div>
                            <h4 class="section-title">Permanent Address</h4>
                            <div class="row g-4 mb-4">
                                <div class="col-12">
                                    <label class="hitech-label">Street Address</label>
                                    <div class="hitech-input-readonly">{{ $selectedUser->perm_street }}</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="hitech-label">State</label>
                                    <div class="hitech-input-readonly">{{ $selectedUser->perm_state }}</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="hitech-label">City</label>
                                    <div class="hitech-input-readonly">{{ $selectedUser->perm_city }}</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="hitech-label">ZIP Code</label>
                                    <div class="hitech-input-readonly">{{ $selectedUser->perm_zip }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Step 3: Banking --}}
                    <div id="audit-step-3" class="audit-content onboarding-card animate__animated animate__fadeIn d-none">
                        <div class="card-header-hitech">
                            <h2>Banking Information</h2>
                            <p>Verify financial disbursement coordinates.</p>
                        </div>
                        <div class="card-body-hitech">
                            @php $bank = $selectedUser->bankAccount; @endphp
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="hitech-label">Bank Name</label>
                                    <div class="hitech-input-readonly">{{ $bank->bank_name ?? 'N/A' }}</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="hitech-label">Account Name</label>
                                    <div class="hitech-input-readonly">{{ $bank->account_name ?? 'N/A' }}</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="hitech-label">Account Number</label>
                                    <div class="hitech-input-readonly font-monospace">{{ $bank->account_number ?? 'N/A' }}</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="hitech-label">IFSC Code</label>
                                    <div class="hitech-input-readonly font-monospace uppercase">{{ $bank->bank_code ?? 'N/A' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Step 4: Documents --}}
                    <div id="audit-step-4" class="audit-content onboarding-card animate__animated animate__fadeIn d-none">
                        <div class="card-header-hitech">
                            <h2>Identity & Certificates</h2>
                            <p>Audit supporting documentation and national IDs.</p>
                        </div>
                        <div class="card-body-hitech">
                            <h4 class="section-title">Identity IDs</h4>
                            <div class="row g-4 mb-4">
                                <div class="col-md-6">
                                    <label class="hitech-label">Aadhaar Number</label>
                                    <div class="hitech-input-readonly">{{ $selectedUser->aadhaar_no ?: 'N/A' }}</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="hitech-label">PAN Number</label>
                                    <div class="hitech-input-readonly uppercase">{{ $selectedUser->pan_no ?: 'N/A' }}</div>
                                </div>
                            </div>
                            <h4 class="section-title">Uploaded Files Vault</h4>
                            <div class="row g-3">
                                @php
                                    $onboardingFolder = \Constants::BaseFolderOnboardingDocuments . $selectedUser->id;
                                    $files = \Illuminate\Support\Facades\Storage::disk('public')->files($onboardingFolder);
                                @endphp
                                @foreach($files as $file)
                                <div class="col-md-6">
                                    <a href="{{ \App\Helpers\FileSecurityHelper::generateSecureUrl($file) }}" target="_blank" class="document-preview-card">
                                        <i class="bx bx-file fs-2 text-primary"></i>
                                        <div class="min-w-0">
                                            <div class="fw-bold text-slate-900 small truncate">{{ basename($file) }}</div>
                                            <div class="text-[9px] text-slate-400 uppercase font-black tracking-widest">Certified Asset</div>
                                        </div>
                                    </a>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    {{-- Step 5: Final Review --}}
                    <div id="audit-step-5" class="audit-content onboarding-card animate__animated animate__fadeIn d-none">
                        <div class="card-header-hitech">
                            <h2>Executive Decisions</h2>
                            <p>Finalize audit and grant system authorization.</p>
                        </div>
                        <div class="card-body-hitech text-center py-10">
                            <div class="w-16 h-16 bg-primary/10 rounded-full flex items-center justify-center mx-auto mb-6">
                                <span class="material-symbols-outlined text-primary text-3xl">verified_user</span>
                            </div>
                            <h3 class="text-xl font-black text-slate-900 mb-2">Audit Complete</h3>
                            <p class="text-slate-500 text-sm max-w-xs mx-auto mb-8">All candidate data has been scrutinized. Select an executive action to conclude the integration.</p>
                            
                            <div class="flex flex-col gap-3 max-w-sm mx-auto">
                                <form method="POST" action="{{ route('employees.onboarding.approve', $selectedUser->id) }}">
                                    @csrf
                                    <button type="submit" class="w-full bg-primary text-white py-4 rounded-2xl text-xs font-black uppercase tracking-widest shadow-xl shadow-primary/20 hover:bg-deep-teal transition-all flex items-center justify-center gap-3">
                                        Confirm & Activate Entry
                                    </button>
                                </form>
                                <button onclick="toggleAuditResubmit()" class="w-full bg-white border-2 border-slate-100 text-slate-400 py-4 rounded-2xl text-xs font-black uppercase tracking-widest hover:border-red-500 hover:text-red-500 transition-all flex items-center justify-center gap-3">
                                    Challenge Submission
                                </button>
                                
                                <div id="audit-resubmit-form" class="hidden mt-4 text-left p-6 bg-red-50 rounded-2xl border border-red-100">
                                    <form method="POST" action="{{ route('employees.onboarding.resubmit', $selectedUser->id) }}">
                                        @csrf
                                        <label class="text-[9px] font-black text-red-600 uppercase tracking-widest mb-2 block">HR FEEDBACK NOTE:</label>
                                        <textarea name="notes" placeholder="Specify required corrections..." class="w-full px-4 py-3 text-sm border-red-200 rounded-xl mb-4 focus:ring-red-500 focus:border-red-500 bg-white" rows="4" required></textarea>
                                        <button type="submit" class="w-full bg-red-600 text-white py-3 rounded-xl text-[10px] font-black uppercase tracking-widest">SEND CHALLENGE</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="hitech-modal-footer">
                <button type="button" id="prevAuditBtn" class="btn-prev-hitech invisible">
                    <i class="bx bx-left-arrow-alt"></i> PREVIOUS
                </button>
                <button type="button" id="nextAuditBtn" class="btn-next-hitech">
                    CONTINUE <i class="bx bx-right-arrow-alt"></i>
                </button>
            </div>
        </div>
    </div>
</div>
@endif
@endsection

@section('page-script')
<script>
    let currentAuditStep = 1;

    function openOnboardingModal() {
        document.getElementById('onboardingInviteModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
    
    function closeOnboardingModal() {
        document.getElementById('onboardingInviteModal').classList.add('hidden');
        document.body.style.overflow = 'auto';
    }

    function updateAuditStepper() {
        // Update Content
        document.querySelectorAll('.audit-content').forEach((el, idx) => {
            if (idx + 1 === currentAuditStep) {
                el.classList.remove('d-none');
            } else {
                el.classList.add('d-none');
            }
        });

        // Update Circles & Lines
        document.querySelectorAll('.step-custom[data-step]').forEach((c, idx) => {
            const stepNum = idx + 1;
            if (stepNum < currentAuditStep) {
                c.classList.add('completed');
                c.classList.remove('active');
            } else if (stepNum === currentAuditStep) {
                c.classList.add('active');
                c.classList.remove('completed');
            } else {
                c.classList.remove('active', 'completed');
            }
        });

        document.querySelectorAll('.stepper-line-audit').forEach((l, idx) => {
            if (idx + 1 < currentAuditStep) {
                l.classList.add('active');
            } else {
                l.classList.remove('active');
            }
        });

        // Update Buttons
        const prevBtn = document.getElementById('prevAuditBtn');
        const nextBtn = document.getElementById('nextAuditBtn');

        if (currentAuditStep === 1) {
            prevBtn.classList.add('invisible');
        } else {
            prevBtn.classList.remove('invisible');
        }

        if (currentAuditStep === 5) {
            nextBtn.innerHTML = 'CLOSE REVIEW';
            nextBtn.onclick = () => {
                const modal = bootstrap.Modal.getInstance(document.getElementById('auditDetailModal'));
                modal.hide();
            };
        } else {
            nextBtn.innerHTML = 'CONTINUE <i class="bx bx-right-arrow-alt"></i>';
            nextBtn.onclick = () => {
                currentAuditStep++;
                updateAuditStepper();
            };
        }
    }

    document.getElementById('prevAuditBtn').addEventListener('click', () => {
        if (currentAuditStep > 1) {
            currentAuditStep--;
            updateAuditStepper();
        }
    });

    function toggleAuditResubmit() {
        document.getElementById('audit-resubmit-form').classList.toggle('hidden');
    }

    document.addEventListener('DOMContentLoaded', function() {
        @if($selectedUser)
            const auditModal = new bootstrap.Modal(document.getElementById('auditDetailModal'));
            auditModal.show();
            updateAuditStepper();
        @endif
    });
</script>
@endsection
