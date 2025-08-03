<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;

class ChatController extends Controller
{
    private $lmStudioUrl = 'http://192.168.100.2:1234/v1/chat/completions';
    private $modelName = 'deepseek/deepseek-r1-0528-qwen3-8b';

    public function sendMessage(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'message' => 'required|string|max:1000',
                'temperature' => 'nullable|numeric|between:0,1',
                'conversation_history' => 'nullable|array'
            ]);

            $message = $request->input('message');
            $temperature = $request->input('temperature', 0.7);
            $conversationHistory = $request->input('conversation_history', []);

            Log::info('Chat request received', [
                'message' => $message,
                'temperature' => $temperature,
                'history_count' => count($conversationHistory)
            ]);

            // Build messages array for LM Studio
            $messages = [
                [
                    'role' => 'system',
                    'content' => 'You are OnlineCareAI, a helpful healthcare assistant. Provide accurate, helpful, and caring responses about health and medical topics. Always recommend consulting healthcare professionals for serious medical concerns.'
                ]
            ];

            // Add conversation history (limit to last 10 messages to avoid token overflow)
            $recentHistory = array_slice($conversationHistory, -10);
            foreach ($recentHistory as $historyItem) {
                if (isset($historyItem['role']) && isset($historyItem['content'])) {
                    $messages[] = [
                        'role' => $historyItem['role'],
                        'content' => $historyItem['content']
                    ];
                }
            }

            // Add current user message
            $messages[] = [
                'role' => 'user',
                'content' => $message
            ];

            $requestData = [
                'model' => $this->modelName,
                'messages' => $messages,
                'temperature' => $temperature,
                'max_tokens' => 2000,
                'stream' => false
            ];

            Log::info('Sending to LM Studio', [
                'url' => $this->lmStudioUrl,
                'messages_count' => count($messages),
                'request_data' => $requestData
            ]);

            // Send request to LM Studio with retry logic
            $response = Http::timeout(60)->retry(2, 1000)->post($this->lmStudioUrl, $requestData);

            if ($response->successful()) {
                $data = $response->json();
                $aiResponse = $data['choices'][0]['message']['content'] ?? 'Sorry, I could not generate a response.';

                Log::info('LM Studio response', [
                    'status' => $response->status(),
                    'successful' => true
                ]);

                // Process chain of thought response
                $processedResponse = $this->processChainOfThoughtResponse($aiResponse);

                Log::info('AI response generated successfully');

                return response()->json([
                    'success' => true,
                    'response' => $processedResponse['final_answer'],
                    'thinking_process' => $processedResponse['thinking'],
                    'model_used' => $this->modelName,
                    'tokens_used' => $data['usage'] ?? null
                ]);
            } else {
                Log::error('LM Studio request failed', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to connect to AI model',
                    'details' => $response->body()
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Chat processing error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'An error occurred while processing your request',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process chain of thought response to separate thinking from final answer
     */
    private function processChainOfThoughtResponse(string $response): array
    {
        // For deepseek-r1, the response often contains thinking process followed by final answer
        // Look for common patterns that separate thinking from final response
        
        $lines = explode("\n", $response);
        $thinking = [];
        $finalAnswer = [];
        $inThinking = false;
        $thinkingFinished = false;
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Skip empty lines at the start
            if (empty($line) && empty($thinking) && empty($finalAnswer)) {
                continue;
            }
            
            // Detect thinking patterns
            if (preg_match('/^(thinking|thought|analysis|reasoning)[:.]?\s*/i', $line) || 
                preg_match('/^let me think/i', $line) ||
                preg_match('/^i need to/i', $line) ||
                preg_match('/^first,?/i', $line) ||
                (!$thinkingFinished && preg_match('/^(the|this|since|because|however|actually)/i', $line))) {
                $inThinking = true;
            }
            
            // Detect end of thinking and start of final answer
            if (preg_match('/^(answer|response|solution|recommendation)[:.]?\s*/i', $line) ||
                preg_match('/^(hi|hello|greetings)/i', $line) ||
                preg_match('/^(here\'s|here is)/i', $line) ||
                ($inThinking && preg_match('/^[A-Z].*[.!?]$/', $line) && !preg_match('/\b(think|consider|analyze|should|would|could|might)\b/i', $line))) {
                $thinkingFinished = true;
                $inThinking = false;
            }
            
            // Categorize the line
            if ($inThinking && !$thinkingFinished) {
                $thinking[] = $line;
            } elseif ($thinkingFinished || (!$inThinking && !empty($finalAnswer))) {
                $finalAnswer[] = $line;
            } elseif (!$inThinking && empty($thinking)) {
                // If no thinking detected, treat as final answer
                $finalAnswer[] = $line;
            }
        }
        
        // If we couldn't separate properly, treat everything as final answer
        if (empty($finalAnswer)) {
            $finalAnswer = $lines;
            $thinking = [];
        }
        
        return [
            'thinking' => !empty($thinking) ? implode("\n", $thinking) : null,
            'final_answer' => implode("\n", $finalAnswer)
        ];
    }

    public function getModelStatus(): JsonResponse
    {
        try {
            $response = Http::timeout(5)->get('http://192.168.100.2:1234/v1/models');
            
            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'status' => 'connected',
                    'models' => $response->json()
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'status' => 'disconnected',
                    'error' => 'Could not connect to LM Studio'
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 'disconnected',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
