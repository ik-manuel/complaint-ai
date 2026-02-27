<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Complaint;
use App\Models\AiResponse;
use App\Services\ComplaintClassifier;
use App\Services\ResponseGenerator;
use App\Services\ConversationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ComplaintController extends Controller
{
    public function __construct(
        private ComplaintClassifier $classifier,
        private ResponseGenerator $responseGenerator,
        private ConversationService $conversationService
    ) {  }

    /**
     * Show complaint submission form
     */
    public function create()
    {
        return view('complaints.create');
    }

    /**
     * Show conversation view for customer follow-ups
     */
    public function conversation($ticketNumber)
    {
        $complaint = Complaint::where('ticket_number', $ticketNumber)
            ->with(['customer', 'conversation.messages'])
            ->firstOrFail();

        return view('complaints.conversation', compact('complaint'));
    }

    /**
     * NEW: Handle customer follow-up message
     */
    public function followUp(Request $request, $ticketNumber)
    {
        $validated = $request->validate([
            'message' => 'required|string|min:5',
        ]);

        $complaint = Complaint::where('ticket_number', $ticketNumber)->firstOrFail();

        // Get or create conversation
        $conversation = $complaint->conversation;
        
        if (!$conversation) {
            $conversation = $this->conversationService->createConversation($complaint);
        }

        try {
            // Feature: AI response with memory!
            $result = $this->conversationService->getAiResponse(
                $conversation,
                $validated['message']
            );

            return back()->with('success', 'Message sent! AI is responding...');

        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to send message: ' . $e->getMessage()]);
        }
    }

    /**
     * Store method to create conversation on initial complaint
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'subject' => 'required|string|max:255',
            'message' => 'required|string|min:10',
        ]);

        try {
            DB::beginTransaction();

            // Find or create customer
            $customer = Customer::firstOrCreate(
                ['email' => $validated['email']],
                ['name' => $validated['name']]
            );

            // Create complaint
            $complaint = $customer->complaints()->create([
                'subject' => $validated['subject'],
                'message' => $validated['message'],
                'status' => 'new',
            ]);

            // AI Classification
            $classification = $this->classifier->classify(
                $complaint->subject,
                $complaint->message
            );

            $complaint->update([
                'urgency' => $classification['urgency'],
                'category' => $classification['category'],
            ]);

            // Create conversation with initial message
            $conversation = $this->conversationService->createConversation($complaint);
            
            // Add initial complaint as first user message
            $this->conversationService->addMessage(
                $conversation,
                'user',
                $complaint->message
            );

            // AI Response Generation (now with conversation context)
            $aiResult = $this->responseGenerator->generate($complaint);

            // Save AI response to conversation
            $this->conversationService->addMessage(
                $conversation,
                'assistant',
                $aiResult['response']
            );

            // Store in old format for backward compatibility
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
