<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
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

            // Build messages array for LM Studio
            $messages = [
                [
                    'role' => 'system',
                    'content' => 'You are OnlineCareAI, a helpful healthcare assistant. Provide accurate, helpful, and caring responses about health and medical topics. Always recommend consulting healthcare professionals for serious medical concerns.'
                ]
            ];

            // Add conversation history
            foreach ($conversationHistory as $historyItem) {
                $messages[] = [
                    'role' => $historyItem['role'],
                    'content' => $historyItem['content']
                ];
            }

            // Add current user message
            $messages[] = [
                'role' => 'user',
                'content' => $message
            ];

            // Send request to LM Studio
            $response = Http::timeout(30)->post($this->lmStudioUrl, [
                'model' => $this->modelName,
                'messages' => $messages,
                'temperature' => $temperature,
                'max_tokens' => -1,
                'stream' => false
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $aiResponse = $data['choices'][0]['message']['content'] ?? 'Sorry, I could not generate a response.';

                return response()->json([
                    'success' => true,
                    'response' => $aiResponse,
                    'model_used' => $this->modelName,
                    'tokens_used' => $data['usage'] ?? null
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to connect to AI model',
                    'details' => $response->body()
                ], 500);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'An error occurred while processing your request',
                'details' => $e->getMessage()
            ], 500);
        }
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
