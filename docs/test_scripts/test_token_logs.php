// test_token_logs.php
<?php

require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== TOKEN USAGE ANALYSIS ===\n\n";

// By operation
$byOperation = \DB::table('token_usage_logs')
    ->selectRaw('
        operation,
        COUNT(*)              AS api_calls,
        SUM(input_tokens)     AS total_input,
        SUM(output_tokens)    AS total_output,
        SUM(total_tokens)     AS total_tokens,
        AVG(total_tokens)     AS avg_tokens,
        SUM(cost_usd)         AS total_cost,
        AVG(cost_usd)         AS avg_cost
    ')
    ->groupBy('operation')
    ->orderByDesc('total_tokens')
    ->get();

echo "BY OPERATION (sorted by total tokens used):\n";
echo str_repeat('-', 80) . "\n";
printf("%-30s %8s %10s %10s %10s %12s\n",
    'Operation', 'Calls', 'Avg Tokens', 'Total Tokens', 'Avg Cost', 'Total Cost');
echo str_repeat('-', 80) . "\n";

foreach ($byOperation as $row) {
    printf("%-30s %8d %10d %10d %10s %12s\n",
        $row->operation,
        $row->api_calls,
        round($row->avg_tokens),
        $row->total_tokens,
        '$' . number_format($row->avg_cost, 6),
        '$' . number_format($row->total_cost, 6)
    );
}

echo "\n";

// Overall totals
$totals = \DB::table('token_usage_logs')
    ->selectRaw('
        COUNT(*)           AS total_calls,
        SUM(total_tokens)  AS total_tokens,
        SUM(cost_usd)      AS total_cost
    ')
    ->first();

echo "OVERALL TOTALS:\n";
echo str_repeat('-', 40) . "\n";
echo "Total API calls:  {$totals->total_calls}\n";
echo "Total tokens:     " . number_format($totals->total_tokens) . "\n";
echo "Total cost:       $" . number_format($totals->total_cost, 6) . "\n\n";

// Most expensive single complaint
$expensive = \DB::table('token_usage_logs')
    ->whereNotNull('complaint_id')
    ->selectRaw('complaint_id, SUM(total_tokens) AS tokens, SUM(cost_usd) AS cost')
    ->groupBy('complaint_id')
    ->orderByDesc('cost')
    ->first();

if ($expensive) {
    $complaint = \App\Models\Complaint::find($expensive->complaint_id);
    echo "MOST EXPENSIVE COMPLAINT:\n";
    echo str_repeat('-', 40) . "\n";
    echo "Ticket:  " . ($complaint->ticket_number ?? 'N/A') . "\n";
    echo "Tokens:  " . number_format($expensive->tokens) . "\n";
    echo "Cost:    $" . number_format($expensive->cost, 6) . "\n\n";
}

echo "=== DONE ===\n";