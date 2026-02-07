<?php

namespace App\Services;

use App\Models\Complaint;

class ResponseGenerator
{
    /**
     * Create a new class instance.
     */
    public function __construct(private GroqService $groq) { }

    /**
     * 
     */
    public function generate(Complaint $complaint): array
    {
        $systemMessage = $this->getSystemMessageForUrgency($complaint->urgency);

        $prompt = "Customer Complaint:
            Subject: {$complaint->subject}
            Message: {$complaint->message}
            Customer: {$complaint->customer->name} 

            Generate a professional response that:
            1. Acknowledges their concern
            2. Shows empathy
            3. Provides a solution or next steps
            4. Keeps it under 150 words

            Response:
        ";

        $result = $this->groq->chat($prompt, [
            'system'      => $systemMessage,
            'temperature' => 0.3,
            'max_tokens'  => 300,
        ]);

        return [
            'response' => $result['content'],
            'tokens'   => $result['tokens'],
        ];
    }

    private function getSystemMessageForUrgency(string $urgency): string
    {
        return match($urgency) {
            'high'   => "You are a senior customer service manager handling urgent complaints.
                         Be direct, empathetic, and action-oriented. Show you take this seriously.",
            'medium' => "You are a professional customer support agent.
                         Be helpful, clear, and solution-focused.",
            'low'    => "You are a friendly customer support representative.
                         Be warm, patient, and informative.",
            default  => "You are a professional customer support agent."
        };
    }

}
 