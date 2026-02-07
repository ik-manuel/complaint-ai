<?php

namespace App\Services;


class ComplaintClassifier
{
    /**
     * Create a new class instance.
     */
    public function __construct(private GroqService $groq) { }

    /**
     * Classify a complaint's urgency and category using Groq API.
     *
     * @param string $subject The complaint subject
     * @param string $message The complaint message
     * @return array ['urgency' => string, 'category' => string]
     */
    public function classify(string $subject, string $message): array
    {
        $prompt = "Classify this customer complaint.
            Examples:
            Complaint: \"My order hasn't arrived in 3 weeks! This is unacceptable!\"
            Urgency: hign
            Category: shipping

            Complaint: \"The product stopped working after 2 days\"
            Urgency: medium
            Category: product_quality

            Now classify:
            Subject: {$subject}
            Message: {$message}

            Return ONLY in this format:
            Urgency: [low/medium/high]
            Category: [billing/shipping/product_quality/technical/other
        ";

        $result = $this->groq->chat($prompt, [
            'temperature' => 0.1,
            'max_tokens'  => 100,
        ]);

        return $this->parseClassification($result['content']);

    }

    /**
     * Parse the Groq response content to extract urgency and category.
     */
    private function parseClassification(string $content): array
    {
        $urgency = 'medium';
        $category = 'other';

        if (preg_match('/Urgency:\s*(low|medium|high)/i', $content, $matches)) {
            $urgency = strtolower($matches[1]);
        }

        if (preg_match('/Category:\s*(\w+)/i', $content, $matches)) {
            $category = strtolower($matches[1]);
        }

        return [
            'urgency'  => $urgency,
            'category' => $category,
        ];
    }
}
