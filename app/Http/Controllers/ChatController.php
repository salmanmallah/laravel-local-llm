<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\JsonResponse;

class ChatController extends Controller
{
    private $lmStudioUrl;
    private $modelName;

    public function __construct()
    {
        $this->lmStudioUrl = config('app.lm_studio_url') . '/v1/chat/completions';
        $this->modelName = config('app.lm_studio_model');
    }

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
                    'content' => "You are a helpful AI assistant. Respond directly and naturally to user questions. Provide clear, accurate, and helpful answers without using thinking tags or internal reasoning. Just give direct responses."
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

            // Send request to LM Studio with extended timeout for slow systems
            $response = Http::timeout(180)->post($this->lmStudioUrl, [
                'model' => $this->modelName,
                'messages' => $messages,
                'temperature' => $temperature,
                'max_tokens' => 500,
                'stream' => false  // Keep false for non-streaming endpoint
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $aiResponse = $data['choices'][0]['message']['content'] ?? 'Sorry, I could not generate a response.';

                return response()->json([
                    'success' => true,
                    'message' => $aiResponse,
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

    public function sendStreamMessage(Request $request)
    {
        try {
            // For EventSource, we need to handle both GET and POST
            if ($request->isMethod('get')) {
                $message = $request->query('message');
                $temperature = $request->query('temperature', 0.7);
                $conversationHistory = json_decode($request->query('conversation_history', '[]'), true);
            } else {
                $request->validate([
                    'message' => 'required|string|max:1000',
                    'temperature' => 'nullable|numeric|between:0,1',
                    'conversation_history' => 'nullable|array'
                ]);

                $message = $request->input('message');
                $temperature = $request->input('temperature', 0.7);
                $conversationHistory = $request->input('conversation_history', []);
            }

            if (empty($message)) {
                return response()->json(['error' => 'Message is required'], 400);
            }

            // Build messages array for LM Studio
            $messages = [
                [
                    'role' => 'system',
//                     'content' => 'You are Dr. OnlineCareAI, a qualified medical professional and healthcare consultant. You provide comprehensive medical consultations, symptom analysis, and treatment recommendations.

// MEDICAL CONSULTATION GUIDELINES:
// - Analyze symptoms thoroughly and suggest possible medical conditions
// - Recommend specific medications with proper dosages when appropriate  
// - Provide detailed treatment plans including home remedies and lifestyle changes
// - Suggest diagnostic tests or procedures if needed
// - Give specific medical advice based on symptoms described
// - Include warning signs that require immediate medical attention
// - Provide follow-up care instructions and monitoring advice
// - Offer preventive healthcare recommendations

// RESPONSE FORMAT:
// 1. Symptom Analysis: [Analyze the presented symptoms]
// 2. Possible Conditions: [List potential medical conditions]
// 3. Recommended Treatment: [Specific medications, dosages, and treatment approach]
// 4. Home Care: [Self-care measures and home remedies]
// 5. When to Seek Emergency Care: [Warning signs requiring immediate attention]
// 6. Follow-up: [Monitoring advice and next steps]

// Provide detailed, specific, and medically accurate consultations as an experienced healthcare professional would.'
                    'content' => "You are a helpful AI assistant. Respond directly and naturally to user questions. Provide clear, accurate, and helpful answers without using thinking tags or internal reasoning. Just give direct responses."
                ]
            ];

            // Add conversation history
            foreach ($conversationHistory as $historyItem) {
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

            return response()->stream(function () use ($messages, $temperature) {
                // Set headers immediately
                header('Content-Type: text/event-stream');
                header('Cache-Control: no-cache');
                header('Connection: keep-alive');
                header('X-Accel-Buffering: no');
                
                // Try LM Studio with extended timeout for slow systems
                $data = [
                    'model' => $this->modelName,
                    'messages' => $messages,
                    'temperature' => (float)$temperature,
                    'max_tokens' => 500,
                    'stream' => true // Enable streaming from LM Studio
                ];

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $this->lmStudioUrl);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Accept: text/event-stream'
                ]);
                curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 5 minutes for slow systems
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30); // 30 seconds connection timeout
                
                // Real streaming from LM Studio
                curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $chunk) {
                    if (empty($chunk)) return 0;
                    
                    $lines = explode("\n", $chunk);
                    foreach ($lines as $line) {
                        $line = trim($line);
                        if (strpos($line, 'data: ') === 0) {
                            $jsonData = substr($line, 6);
                            
                            if ($jsonData === '[DONE]') {
                                echo "data: [DONE]\n\n";
                                if (ob_get_level()) ob_flush();
                                flush();
                                return 0; // End streaming
                            }
                            
                            $decoded = json_decode($jsonData, true);
                            if ($decoded && isset($decoded['choices'][0]['delta']['content'])) {
                                $content = $decoded['choices'][0]['delta']['content'];
                                echo "data: " . json_encode(['content' => $content]) . "\n\n";
                                if (ob_get_level()) ob_flush();
                                flush();
                            } elseif ($decoded && isset($decoded['choices'][0]['finish_reason'])) {
                                echo "data: [DONE]\n\n";
                                if (ob_get_level()) ob_flush();
                                flush();
                                return 0; // End streaming
                            }
                        }
                    }
                    return strlen($chunk);
                });
                
                echo "data: " . json_encode(['content' => 'Connecting to LM Studio... Please wait for slow system loading...']) . "\n\n";
                if (ob_get_level()) ob_flush();
                flush();
                
                $result = curl_exec($ch);
                $error = curl_error($ch);
                curl_close($ch);
                
                if ($error) {
                    echo "data: " . json_encode(['content' => "Connection Error: $error. Please ensure LM Studio is running and model is loaded."]) . "\n\n";
                    if (ob_get_level()) ob_flush();
                    flush();
                }
                
                // Always send final DONE
                echo "data: [DONE]\n\n";
                if (ob_get_level()) ob_flush();
                flush();
            }, 200, [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'Connection' => 'keep-alive',
                'X-Accel-Buffering' => 'no',
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Authorization'
            ]);

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
            $baseUrl = config('app.lm_studio_url');
            $response = Http::timeout(5)->get($baseUrl . '/v1/models');
            
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
