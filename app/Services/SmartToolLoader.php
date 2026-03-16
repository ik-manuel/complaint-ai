<?php

namespace App\Services;

class SmartToolLoader
{
    /**
     * Create a new class instance.
     */
    public function __construct(private ToolService $toolService) { }

    /**
     * Get tools for a standalone question (no conversation context)
     * Used in: general queries
     */
    public function getRelevantTools(string $question): array
    {
        $question = strtolower($question);
        $relevantTools = [];
        $allTools = $this->toolService->getAvailableTools();

        // Math patherns => Load calculator
        if (preg_match('/\b(\d+|calculate|multiply|divide|add|subtract|percent|times|\+|\-|\*|\/)\b/i', $question)) {
            $relevantTools[] = $this->findTool($allTools, 'calculate');
        }

        // Time patterns → Load time tool
        if (preg_match('/\b(time|date|clock|timezone|what day|when|current|today|now)\b/i', $question)) {
            $relevantTools[] = $this->findTool($allTools, 'get_current_time');
        }

        // String patterns => Load string tool
        if (preg_match('/\b(how many|count|length|characters|letters|words)\b/i', $question)) {
            $relevantTools[] = $this->findTool($allTools, 'get_string_length');
        }

        // DATABASE PATTERNS
        // Complaint lookup patterns
        if (preg_match('/\b(ticket|complaint|TKT-|order)\s*(number|#|id)?\s*[A-Z0-9\-]+/i', $question)) {
            $relevantTools[] = $this->findTool($allTools, 'get_complaint_by_ticket');
        }

        // Customer history patterns
        if (preg_match('/\b(customer|user|person).*\b(complaints|history|tickets|orders)/i', $question)) {
            $relevantTools[] = $this->findTool($allTools, 'get_customer_complaints');
        }

        // Search/filter complaints
        if (preg_match('/\b(find|search|show|list|get).*\b(complaints|tickets)/i', $question)) {
            $relevantTools[] = $this->findTool($allTools, 'search_complaints');
        }

        // Statistics patterns
        if (preg_match('/\b(how many|count|total|statistics|stats|number of).*\b(complaints|tickets)/i', $question)) {
            $relevantTools[] = $this->findTool($allTools, 'get_complaint_statistics');
        }

        // Filter out nulls
        $relevantTools = array_filter($relevantTools);

        \Log::info('Smart tool loading:', [
            'question'           => $question,
            'all_tools_count'    => count($allTools),
            'loaded_tools_count' => count($relevantTools),
            'loaded_tools'       => array_map(fn($t) => $t['function']['name'], $relevantTools),
            'token_savings'      => (count($allTools) - count($relevantTools)) * 50 . ' tokens saved',
        ]);

        return $relevantTools;
    }

    /**
     * Get tools for CONVERSATION context (smarter, context-aware)
     * Used in: ConversationService
     */
    public function getConversationTools(string $userMessage, array $context = []): array
    {
        $allTools = $this->toolService->getAvailableTools();
        $tools = [];

        // === LAYER 1: Context-based tools (load based on what we know) ===

        // If we're in a complaint conversation, always make ticket lookup available
        if (!empty($context['ticket_number'])) {
            $tools[] = $this->findTool($allTools, 'get_complaint_by_ticket');
        }

        // === LAYER 2: Message-based tools (load based on what user asked) ===

        $message = strtolower($userMessage);

        // Math in message
        if (preg_match('/\b(\d+.*[\+\-\*\/]|calculate|percent|times|multiply|divide)\b/i', $message)) {
            $tools[] = $this->findTool($allTools, 'calculate');
        }

        // Time in message
        if (preg_match('/\b(time|date|when|today|now|current)\b/i', $message)) {
            $tools[] = $this->findTool($allTools, 'get_current_time');
        }

        // Search/filter complaints in message
        if (preg_match('/\b(find|search|all complaints|other complaints|similar)\b/i', $message)) {
            $tools[] = $this->findTool($allTools, 'search_complaints');
            $tools[] = $this->findTool($allTools, 'get_complaint_statistics');
        }

        // Remove nulls and duplicates
        $tools = array_values(array_unique(array_filter($tools), SORT_REGULAR));

        \Log::info('SmartToolLoader - Conversation tools selected:', [
            'message'                 => $userMessage,
            'context_provided'        => array_keys($context),
            'tools_selected'          => array_map(fn($t) => $t['function']['name'], $tools),
            'total_tools_available'   => count($allTools),
            'tools_loaded'            => count($tools),
            'estimated_token_savings' => (count($allTools) - count($tools)) * 50 . ' tokens',
        ]);

        return $tools;
    }

    /**
     * Find specific tool by name
     */
    private function findTool(array $tools, string $name): ?array 
    {
        foreach ($tools as $tool) {
            if ($tool['function']['name'] === $name) {
                return $tool;
            }
        }
        return null;
    }

    /**
     * Decide if ANY tools are needed
     */
    public function needsTools(string $question): bool
    {
        return count($this->getRelevantTools($question)) > 0;
    }

    /**
     * Organize tools by category
     */
    public function getToolsByCategory(string $category): array
    {
        return match($category) {
            'math' => ['calculate'],
            'time' => ['get_current_time'],
            'string' => ['get_string_length'],
            'database' => [
                'get_complaint_by_ticket',
                'get_customer_complaints', 
                'search_complaints',
                'get_complaint_statistics'
            ],
            'all' => $this->toolService->getAvailableTools(),
            default => [],
        };
    }

}
