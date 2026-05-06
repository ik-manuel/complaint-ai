@extends('layouts.app')

@section('title', 'Cost Dashboard - ComplaintAI')

@section('content')

    <h2 class="text-2xl font-bold text-gray-800 mb-6">💰 Cost Dashboard</h2>

    {{-- Monthly Total --}}
    <div class="grid grid-cols-3 gap-4 mb-8">
        <div class="bg-white rounded-lg shadow p-6 text-center">
            <div class="text-3xl font-bold text-blue-600">
                ${{ number_format($monthlyCost, 4) }}
            </div>
            <div class="text-sm text-gray-500 mt-1">This Month</div>
        </div>
        <div class="bg-white rounded-lg shadow p-6 text-center">
            <div class="text-3xl font-bold text-purple-600">
                {{ number_format($byOperation->sum('api_calls')) }}
            </div>
            <div class="text-sm text-gray-500 mt-1">API Calls This Month</div>
        </div>
        <div class="bg-white rounded-lg shadow p-6 text-center">
            <div class="text-3xl font-bold text-green-600">
                {{ number_format($byOperation->sum('total_tokens')) }}
            </div>
            <div class="text-sm text-gray-500 mt-1">Tokens This Month</div>
        </div>
    </div>

    {{-- By Operation --}}
    <div class="bg-white rounded-lg shadow mb-8">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="font-semibold text-gray-800">Cost by Operation</h3>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="text-left px-6 py-3 text-gray-600">Operation</th>
                    <th class="text-right px-6 py-3 text-gray-600">API Calls</th>
                    <th class="text-right px-6 py-3 text-gray-600">Avg Tokens</th>
                    <th class="text-right px-6 py-3 text-gray-600">Total Tokens</th>
                    <th class="text-right px-6 py-3 text-gray-600">Total Cost</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($byOperation as $row)
                    <tr>
                        <td class="px-6 py-3 font-medium text-gray-800">
                            {{ Str::headline($row->operation) }}
                        </td>
                        <td class="px-6 py-3 text-right text-gray-600">
                            {{ number_format($row->api_calls) }}
                        </td>
                        <td class="px-6 py-3 text-right text-gray-600">
                            {{ number_format($row->avg_tokens) }}
                        </td>
                        <td class="px-6 py-3 text-right text-gray-600">
                            {{ number_format($row->total_tokens) }}
                        </td>
                        <td class="px-6 py-3 text-right font-medium text-blue-600">
                            ${{ number_format($row->total_cost, 6) }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="bg-gray-50 border-t-2 border-gray-200">
                <tr>
                    <td class="px-6 py-3 font-bold text-gray-800">Total</td>
                    <td class="px-6 py-3 text-right font-bold">
                        {{ number_format($byOperation->sum('api_calls')) }}
                    </td>
                    <td class="px-6 py-3"></td>
                    <td class="px-6 py-3 text-right font-bold">
                        {{ number_format($byOperation->sum('total_tokens')) }}
                    </td>
                    <td class="px-6 py-3 text-right font-bold text-blue-600">
                        ${{ number_format($byOperation->sum('total_cost'), 6) }}
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>

    {{-- Daily totals --}}
    @if($dailyTotals->isNotEmpty())
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="font-semibold text-gray-800 mb-4">Daily Cost (Last 30 Days)</h3>
            <div class="space-y-2">
                @foreach($dailyTotals->sortByDesc('date')->take(14) as $day)
                    <div class="flex items-center gap-3">
                        <span class="text-xs text-gray-500 w-24">{{ $day->date }}</span>
                        <div class="flex-1 bg-gray-100 rounded-full h-2">
                            <div class="bg-blue-500 h-2 rounded-full"
                                 style="width: {{ min(($day->cost / max($dailyTotals->max('cost'), 0.0001)) * 100, 100) }}%">
                            </div>
                        </div>
                        <span class="text-xs font-medium text-blue-600 w-20 text-right">
                            ${{ number_format($day->cost, 6) }}
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

@endsection