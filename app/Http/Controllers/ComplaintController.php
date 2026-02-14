<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Complaint;
use App\Models\AiResponse;
use App\Services\ComplaintClassifier;
use App\Services\ResponseGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ComplaintController extends Controller
{
    public function __construct(
        private ComplaintClassifier $classifier,
        private ResponseGenerator $responseGenerator
    ) { }

    /**
     * Show complaint submission form
     */
    public function create()
    {
        return view('complaints.create');
    }

    /**
     * Store new complaint and process with AI
     */
    public function store(Request $request)
    {
        // Validate input
        $validated = $request->validate([
            'name'    => 'required|string|max:255',
            'email'   => 'required|email|max:255',
            'subject' => 'required|string|max:255',
            'message' => 'required|string|min:10',
        ]);

        try {
            DB::beginTransaction();

            // Find or create customer
            $customer = Customer::firstOrCreate(
                ['email' => $validated['email']],
                ['name'  => $validated['name']]
            );

            // Create complaint
            $complaint = $customer->complaints()->create([
                'subject' => $validated['subject'],
                'message' => $validated['message'],
                'status'  => 'new',
            ]);

            // AI Classification
            $classification = $this->classifier->classify(
                $complaint->subject,
                $complaint->message
            );

            $complaint->update([
                'urgency'  => $classification['urgency'],
                'category' => $classification['category'],
            ]);

            // AI Response Generation
            $aiResult = $this->responseGenerator->generate($complaint);

            // Store AI response
            AiResponse::create([
                'complaint_id'  => $complaint->id,
                'response_text' => $aiResult['response'],
                'tokens_used'   => $aiResult['tokens'],
                'approved'      => false,
            ]);

            DB::commit();

            return redirect()
                ->route('complaint.success', $complaint->ticket_number)
                ->with('success', 'Complaint submitted successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            
            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to submit complaint: ' . $e->getMessage()]);
        }
    }

    /**
     * Show success page with ticket number
     */
    public function success($ticketNumber)
    {
        $complaint = Complaint::where('ticket_number', $ticketNumber)->firstOrFail();
        
        return view('complaints.success', compact('complaint'));
    }
}
