<?php

namespace App\Services;

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
            [
                'type'  => 'function',
                'function' => [
                    'name' => 'calculate',
                    'description' => 'Perform basic mathematical calculations. Supports +, -, *, /',
                    'parameters'  => [
                        'type' => 'object',
                        'properties' => [
                            'expression' => [
                                'type' => 'string',
                                'description' => 'The mathematical expression to evaluate. e.g., "15 * 7" or "100 / 4" ',
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
                    'description' => 'Get the current date and time',
                    'parameters'  => [
                        'type' => 'object',
                        'properties' => [
                            'timezone' => [
                                'type' => 'string',
                                'description' => 'The timezone, e.g., "Africa/Lagos", "UTC" ',
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
            ]
        ];
    }

    /**
     * Execute a tool/function call
     */
    public function executeTool(string $functionName, array $arguments): array
    {
        \Log::info('Executing tool:', [
            'function'  => $functionName,
            'arguments' => $arguments,
        ]);

        return match($functionName) {
            'calculate' => $this->calculate($arguments['expression']),
            'get_current_time' => $this->getCurrentTime($arguments['timezone'] ?? 'UTC'),
            'get_string_length' => $this->getStringLength($arguments['text']),
            default => ['error' => 'Unknown function: ' . $functionName],
        };
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
    private function getCurrentTime(string $timezone): array
    {
        try {
            $dt = new \DateTime('now', new \DateTimeZone($timezone));

            return [
                'timezone'  => $timezone,
                'datetime'  => $dt->format('Y-m-d H:i:s'),
                'timestamp' => $dt->getTimestamp(),
                'formatted' => $dt->format('l, F j, Y \a\t g:i A T'),
            ];
        } catch (\Exception $e) {
            return ['error' => 'Invalid timezone: ' . $timezone];
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
