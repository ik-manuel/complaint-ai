<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TokenLogger
{
    /**
     * Model pricing per million tokens (USD)
     * Update these if pricing changes
     */
    private const INPUT_COST_PER_M = 0.59;
    private const OUTPUT_COST_PER_M = 0.79;

    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        // UPDATE PARA TO ENV INSTANTIATE HERE LATER
    }

    /**
     * Log token usage for an API call.
     * 
     * @param string     $operation         What triggered this call
     * @param int        $inputTokens       prompt tokens
     * @param int        $outputTokens      Completeion tokens
     * @param int|null   $complaintId       Associated complaint
     * @param int|null   $conversationId    Associated conversation
     * @param array      $metadata          Additional context
     */
    public function log(
        string $operation,
        int $inputTokens,
        int $outputTokens,
        ?int $complaintId = null,
        ?int $conversationId = null,
        array $metadata = []
    ): void {
        $totalTokens = $inputTokens + $outputTokens;
        $costUsd = $this->calculateCost($inputTokens, $outputTokens);

        try {
            DB::table('token_usage_logs')->insert([
                'operation'       => $operation,
                'complaint_id'    => $complaintId,
                'conversation_id' => $conversationId,
                'input_tokens'    => $inputTokens,
                'output_tokens'   => $outputTokens,
                'total_tokens'    => $totalTokens,
                'cost_usd'        => $costUsd,
                'model'           => config('services.groq.model', 'llama-3.3-70b-versatile'),
                'metadata'        => !empty($metadata) ? json_encode($metadata) : null,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

        } catch (\Exception $e) {
            // Never let logging failure crash the main operation
            Log::error('TokenLogger: failed to log usage', [
                'operation' => $operation,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    /**
     * Calculate cost in USD for given token usage.
     */
    public function calculateCost(int $inputTokens, int $outputTokens): float 
    {
        $inputCost = ($inputTokens / 1_000_000) * self::INPUT_COST_PER_M;
        $outputCost = ($outputTokens / 1_000_000) * self::INPUT_COST_PER_M;

        return round($inputCost + $outputCost, 8);
    }

    /**
     * Get total cost for a specific complaint's entire lifecycle.
     */
    public function getComplaintCost(int $complaintId): array
    {
        $rows = DB::table('token_usage_logs')
            ->where('complaint_id', $complaintId)
            ->selectRaw('
                operation,
                SUM(total_tokens) as total_tokens,
                SUM(cost_usd)     as total_cost,
                COUNT(*)          as api_calls
            ')
            ->groupBy('operation')
            ->get();

        $totalCost = $rows->sum('total_cost');
        $totalTokens = $rows->sum('total_tokens');

        return [
            'complaint_id' => $complaintId,
            'total_tokens' => $totalTokens,
            'total_cost'   => round($totalCost, 6),
            'by_operation' => $rows->toArray(),
        ];
    }

    /**
     * Get operation summary for this month
     */
    public function byOperation()//: Collection
    {
        // By operation this month
        return DB::table('token_usage_logs')
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->selectRaw('
                operation,
                COUNT(*)           AS api_calls,
                SUM(total_tokens)  AS total_tokens,
                AVG(total_tokens)  AS avg_tokens,
                SUM(cost_usd)      AS total_cost
            ')
            ->groupBy('operation')
            ->orderByDesc('total_cost')
            ->get();
    }

    /**
     * Get daily cost summary for the admin dashboard.
     */
    public function getDailySummary(int $days = 30): Collection
    {
        return DB::table('token_usage_logs')
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw("
                DATE(created_at)  AS date,
                SUM(total_tokens) AS total_tokens,
                SUM(cost_usd)     AS total_cost,
                COUNT(*)          AS api_calls,
                AVG(total_tokens) AS avg_tokens,
                operation
            ")
            ->groupBy('date', 'operation')
            ->orderBy('date', 'desc')
            ->get();
    }

    /**
     * Get this month's total cost.
     */
    public function getMonthlyCost(): float
    {
        return (float) DB::table('token_usage_logs')
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->sum('cost_usd');
    }

    /**
     * Daily total for chart
     */
    public function dailyChart()
    {
        return DB::table('token_usage_logs')
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw("
                DATE(created_at) AS date, 
                SUM(cost_usd) AS cost
            ")
            ->groupBy('date')
            ->orderBy('date')
            ->get();
    }

}
