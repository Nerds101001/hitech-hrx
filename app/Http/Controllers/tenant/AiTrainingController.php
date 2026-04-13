<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use App\Models\KnowledgeBase;
use Illuminate\Http\Request;

class AiTrainingController extends Controller
{
    public function index()
    {
        $knowledges = KnowledgeBase::where('category', '!=', 'SYSTEM_PROMPT')->latest()->paginate(10);
        $systemPrompt = KnowledgeBase::where('category', 'SYSTEM_PROMPT')->first();
        
        return view('tenant.ai-training.index', compact('knowledges', 'systemPrompt'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'required|string',
            'content' => 'required|string',
        ]);

        KnowledgeBase::updateOrCreate(
            [
                'category' => $request->input('category'),
                'title' => $request->input('title'),
                'tenant_id' => auth()->user()->tenant_id ?? 1
            ],
            [
                'content' => $request->input('content'),
            ]
        );

        return redirect()->back()->with('success', 'AI Knowledge updated successfully.');
    }

    public function updateInstructions(Request $request)
    {
        $request->validate([
            'content' => 'required|string',
        ]);

        KnowledgeBase::updateOrCreate(
            [
                'category' => 'SYSTEM_PROMPT',
                'tenant_id' => auth()->user()->tenant_id ?? 1
            ],
            [
                'title' => 'Core Brain Instructions',
                'content' => $request->input('content'),
            ]
        );

        return redirect()->back()->with('success', 'Sentinel Brain instructions updated.');
    }

    public function destroy($id)
    {
        KnowledgeBase::findOrFail($id)->delete();
        return redirect()->back()->with('success', 'Knowledge snippet removed.');
    }
}
