<?php

namespace App\Services;

use App\Models\Complaint;
use App\Models\Customer;
use Throwable;
use Exception;

class ToolService
{
    /**
     * Define available tools/functions for AI
     */
    public function getAvailableTools(): array
    {
        return [
            // Existing tools
            [
                'type' => 'function',
                'function' => [
                    'name' => 'calculate',
                    'description' => 'Perform basic mathematical calculations. Supports +, -, *, /',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'expression' => [
                                'type' => 'string',
                                'description' => 'The mathematical expression to evaluate, e.g., "15 * 7" or "100 / 4"',
                            ],
                        ],
                        'required' => ['expression'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_current_time',
                    'description' => 'Get the current date and time. If no timezone is specified, returns UTC time.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'timezone' => [
                                'type' => 'string',
                                'description' => 'The timezone (optional). Examples: "Africa/Lagos", "America/New_York", "UTC".',
                            ],
                        ],
                        'required' => [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_string_length',
                    'description' => 'Count the number of characters in a text string',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'text' => [
                                'type' => 'string',
                                'description' => 'The text to count characters in',
                            ],
                        ],
                        'required' => ['text'],
                    ],
                ],
            ],
            
            // NEW DATABASE TOOLS
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_complaint_by_ticket',
                    'description' => 'Look up a complaint by its ticket number. Returns full complaint details including status, urgency, and AI responses.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'ticket_number' => [
                                'type' => 'string',
                                'description' => 'The ticket number in format TKT-XXXXX',
                            ],
                        ],
                        'required' => ['ticket_number'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_customer_complaints',
                    'description' => 'Get all complaints submitted by a specific customer. Useful for seeing complaint history.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'email' => [ 
                                'type' => 'string',
                                'description' => 'The customer\'s email address',
                            ],
                        ],
                        'required' => ['email'], 
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'search_complaints',
                    'description' => 'Search for complaints by category, urgency, or status. Returns matching complaints.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'category' => [
                                'type' => 'string',
                                'description' => 'Filter by category: billing, shipping, product_quality, technical, other',
                            ],
                            'urgency' => [
                                'type' => 'string',
                                'description' => 'Filter by urgency: low, medium, high',
                            ],
                            'status' => [
                                'type' => 'string',
                                'description' => 'Filter by status: new, responded, resolved',
                            ],
                        ],
                        'required' => [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_complaint_statistics',
                    'description' => 'Get statistics about complaints (total count, by status, by urgency, etc.)',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => (object)[],
                        'required' => [],
                    ],
                ],
            ],
        ];
    }

    /**
     * Execute a tool/function call
     */
    public function executeTool(string $functionName, array $arguments): array
    {
        return match($functionName) {
            'calculate'                => $this->calculate($arguments['expression']),
            'get_current_time'         => $this->getCurrentTime($arguments['timezone'] ?? null),
            'get_string_length'        => $this->getStringLength($arguments['text']),

            // Database tools
            'get_complaint_by_ticket'  => $this->getComplaintByTicket($arguments['ticket_number']),
            'get_customer_complaints'  => $this->getCustomerComplaints($arguments['email']),
            'search_complaints'        => $this->searchComplaints($arguments),
            'get_complaint_statistics' => $this->getComplaintStatistics(),

            default => ['error'        => 'Unknown function: ' . $functionName],
        };
    }

    /**
     * DATABASE TOOL: Get complaint by ticket number
     */
    private function getComplaintByTicket(string $ticketNumber): array
    {
        try {
            $complaint = Complaint::where('ticket_number', $ticketNumber)
                ->with(['customer', 'aiResponse'])
                ->first();

            if (!$complaint) {
                return [
                    'found'   => false,
                    'message' => "No complaint found with ticket number: {$ticketNumber}",
                ];
            }

            return [
                'found'                => true,
                'ticket_number'        => $complaint->ticket_number,
                'subject'              => $complaint->subject,
                'status'               => $complaint->status,
                'urgency'              => $complaint->urgency,
                'category'             => $complaint->category,
                'customer_name'        => $complaint->customer->name,
                'customer_email'       => $complaint->customer->email,
                'submitted_at'         => $complaint->created_at->format('Y-m-d H:i:s'),
                'has_ai_response'      => $complaint->aiResponse !== null,
                'ai_response_approved' => $complaint->aiResponse?->approved ?? false,
            ];

        } catch (\Exception $e) {
            return ['error' => 'Database error: ' . $e->getMessage()];
        }
    }

    /**
     * DATABASE TOOL: Get all complaints for a customer
     */
    private function getCustomerComplaints(string $email): array
    {
        try {
            // Validate input
            if (empty($email)) {
                return [
                    'found' => false,
                    'message' => 'Email address is required',
                ];
            }

            $customer = Customer::where('email', $email)->first();

            if (!$customer) {
                return [
                    'found'   => false,
                    'message' => "No customer found with email: {$email}",
                ];
            }

            $complaints = $customer->complaints()
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function($c) {
                    return [
                        'ticket_number' => $c->ticket_number,
                        'subject'       => $c->subject,
                        'status'        => $c->status,
                        'urgency'       => $c->urgency,
                        'submitted_at'  => $c->created_at->format('y-m-d'),
                    ];
                });

            return [
                'found'            => true,
                'customer_name'    => $customer->name,
                'customer_email'   => $customer->email,
                'total_complaints' => $complaints->count(),
                'complaints'       => $complaints->toArray(),
            ];

        } catch (\Exception $e) {
            return ['error' => 'Database error: ' . $e->getMessage()];
        }
    }

    /**
     * DATABASE TOOL: Search complaints by criteria
     */
    private function searchComplaints(array $filters): array
    {
        try {
            $query = Complaint::query();

            if (isset($filters['category'])) {
                $query->where('category', $filters['category']);
            }
            if (isset($filters['urgency'])) {
                $query->where('urgency', $filters['urgency']);
            }
            if (isset($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            $complaints = $query->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->map(function($c) {
                    return [
                        'ticket_number' => $c->ticket_number,
                        'subject'       => $c->subject,
                        'status'        => $c->status,
                        'urgency'       => $c->urgency,
                        'category'      => $c->category,
                    ];
                });
            
            return [
                'filters_applied' => $filters,
                'results_count'   => $complaints->count(),
                'complaints'      => $complaints->toArray(),
            ];

        } catch (\Exception $e) {
            return ['error' => 'Database error: ' . $e->getMessage()];
        }
    }

    /**
     * DATABASE TOOL: Get complaint statistics
     */
    private function getComplaintStatistics(): array
    {
        try {
            return [
                'total_complaints' => Complaint::count(),
                'by_status' => [
                    'new'       => Complaint::where('status', 'new')->count(),
                    'responded' => Complaint::where('status', 'responded')->count(),
                    'resolved'  => Complaint::where('status', 'resolved')->count(),
                ],
                'by_urgency' => [
                    'low'    => Complaint::where('urgency', 'low')->count(),
                    'medium' => Complaint::where('urgency', 'medium')->count(),
                    'high'   => Complaint::where('urgency', 'high')->count(),
                ],
                'by_category' => Complaint::select('category', \DB::raw('count(*) as count'))
                    ->groupBy('category')
                    ->pluck('count', 'category')
                    ->toArray(),
            ];

        } catch (\Exception $e) {
            return ['error' => 'Database error: ' . $e->getMessage()];
        }
    }

    /**
     * Calculate tool implementation
     */
    private function calculate(string $expression): array
    {
        try {
            // Security: Only allow safe math operations
            if (!preg_match('/^[\d\s\+\-\*\/\(\)\.]+$/', $expression)) {
                return ['error' => 'Invalid expression. Only numbers and +, -, *, / are allowed.'];
            }

            // Evaluate the expression safely
            $result = eval("return {$expression};");

            return [
                'expression' => $expression,
                'result'     => $result,
                'formatted'  => "{$expression} = {$result}",
            ];
        } catch (\Throwable $e) {
            return ['error' => 'Calculation error: ' . $e->getMessage()];
        }
    }

    /**
     * Get current time tool implementation
     */
    private function getCurrentTime($timezone = null): array
    {
        try {
            // Default to UTC if no timezone provided
            $tz = $timezone ?: 'UTC';
            
            \Log::info('Getting current time:', [
                'timezone_requested' => $timezone,
                'timezone_using' => $tz
            ]);
            
            $dt = new \DateTime('now', new \DateTimeZone($tz));
            
            return [
                'timezone' => $tz,
                'datetime' => $dt->format('Y-m-d H:i:s'),
                'timestamp' => $dt->getTimestamp(),
                'formatted' => $dt->format('l, F j, Y \a\t g:i A T'),
            ];

        } catch (\Exception $e) {
            // If invalid timezone, fall back to UTC
            \Log::warning('Invalid timezone, using UTC:', [
                'requested' => $timezone,
                'error'     => $e->getMessage()
            ]);
            
            $dt = new \DateTime('now', new \DateTimeZone('UTC'));
            
            return [
                'timezone'  => 'UTC (fallback)',
                'datetime'  => $dt->format('Y-m-d H:i:s'),
                'timestamp' => $dt->getTimestamp(),
                'formatted' => $dt->format('l, F j, Y \a\t g:i A T'),
            ];
        }
    }

    /**
     * Get string length
     */
    private function getStringLength(string $text): array
    {
        return [
            'text' => $text,
            'length' => strlen($text),
            'words' => str_word_count($text),
        ];
    }
}
