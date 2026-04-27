<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use Illuminate\Http\Request;
use App\Services\EmbeddingService;

class AdminController extends Controller
{
    public function __construct(private EmbeddingService $embeddingService) {}

    /**
     * Show admin dashboard with all complaints
     */
    public function index()
    {
        $complaints = Complaint::with(['customer', 'aiResponse'])
            ->latest()
            ->paginate(20);

        $stats = [
            'total' => Complaint::count(),
            'new' => Complaint::where('status', 'new')->count(),
            'responded' => Complaint::where('status', 'responded')->count(),
            'resolved' => Complaint::where('status', 'resolved')->count(),
            'high_urgency' => Complaint::where('urgency', 'high')->count(),
        ];

        return view('admin.index', compact('complaints', 'stats'));
    }

    /**
     * Show single complaint with AI response
     */
    public function show(Complaint $complaint)
    {
        $complaint->load(['customer', 'aiResponse', 'conversation.messages']);

        $similarComplaints =  collect();

        try {
            $similarComplaints = $this->embeddingService->findSimilarComplaints($complaint, 3);
            \Log::info('AdminController: similar complaints loaded', [
                'count' => $similarComplaints->count(),
            ]);

        } catch (\Exception $e) {
            \Log::error('AdminController: similar complaints failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return view('admin.show', compact('complaint', 'similarComplaints'));
    }

    /**
     * Approve AI response and mark as ready to send
     */
    public function approve(Complaint $complaint)
    {
        $aiResponse = $complaint->aiResponse;

        if (!$aiResponse) {
            return back()->withErrors(['error' => 'No AI response found']);
        }

        $aiResponse->update(['approved' => true]);
        $complaint->update(['status' => 'responded']);

        return back()->with('success', 'Response approved!');
    }

    /**
     * Update AI response text (human editing)
     */
    public function updateResponse(Request $request, Complaint $complaint)
    {
        $validated = $request->validate([
            'response_text' => 'required|string',
        ]);

        $aiResponse = $complaint->aiResponse;

        if (!$aiResponse) {
            return back()->withErrors(['error' => 'No AI response found']);
        }

        $aiResponse->update([
            'response_text' => $validated['response_text'],
        ]);

        return back()->with('success', 'Response updated!');
    }

    /**
     * Mark complaint as resolved
     */
    public function resolve(Complaint $complaint)
    {
        $complaint->update(['status' => 'resolved']);

        return back()->with('success', 'Complaint marked as resolved!');
    }
}