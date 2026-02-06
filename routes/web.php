<?php

use Illuminate\Support\Facades\Route;
use App\Services\GroqService;

Route::get('/', function () {
    return view('welcome');
});

// Test api
Route::get('test-ai', function() {
    $groq = new GroqService();

    $prompt = "Say hello in one sentence!";

    // try {
        $result = $groq->chat( $prompt, [
            'temperature' => 0.7,
            'max_tokens'  => 50,
        ]);

        return response()->json([
            'success'  => true,
            'response' => $result['content'],
            'tokens'   => $result['tokens'],
        ]);
    // } catch (Exception $e) {
    //     return response()->json([
    //         'success' => false,
    //         'error'   => $e->getMessage()
    //     ], 500);
    // }
});
