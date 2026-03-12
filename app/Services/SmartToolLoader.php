<?php

namespace App\Services;

class SmartToolLoader
{
    /**
     * Create a new class instance.
     */
    public function __construct(private ToolService $toolService) { }

    /**
     * Only load relevant tools based on question
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
        
        // Complaint lookup by ticket
        if (preg_match('/\b(ticket|complaint|TKT-|order)\s*(number|#|id)?\s*[A-Z0-9\-]+/i', $question)) {
            $relevantTools[] = $this->findTool($allTools, 'get_complaint_by_ticket');
        }

        // Customer history
        if (preg_match('/\b(customer|user|person).*\b(complaints|history|tickets|orders)/i', $question)) {
            $relevantTools[] = $this->findTool($allTools, 'get_customer_complaints');
        }

        // Search/filter complaints
        if (preg_match('/\b(find|search|show|list|get).*\b(complaints|tickets)/i', $question)) {
            $relevantTools[] = $this->findTool($allTools, 'search_complaints');
        }

        // Statistics
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
