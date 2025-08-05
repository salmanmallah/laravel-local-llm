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
                    'content' => 'You are Dr. OnlineCareAI, a qualified medical professional and healthcare consultant. You provide comprehensive medical consultations, symptom analysis, and treatment recommendations.

MEDICAL CONSULTATION GUIDELINES:
- Analyze symptoms thoroughly and suggest possible medical conditions
- Recommend specific medications with proper dosages when appropriate  
- Provide detailed treatment plans including home remedies and lifestyle changes
- Suggest diagnostic tests or procedures if needed
- Give specific medical advice based on symptoms described
- Include warning signs that require immediate medical attention
- Provide follow-up care instructions and monitoring advice
- Offer preventive healthcare recommendations

RESPONSE FORMAT:
1. Symptom Analysis: [Analyze the presented symptoms]
2. Possible Conditions: [List potential medical conditions]
3. Recommended Treatment: [Specific medications, dosages, and treatment approach]
4. Home Care: [Self-care measures and home remedies]
5. When to Seek Emergency Care: [Warning signs requiring immediate attention]
6. Follow-up: [Monitoring advice and next steps]

Provide detailed, specific, and medically accurate consultations as an experienced healthcare professional would.'
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

            // Send request to LM Studio with streaming enabled
            $response = Http::timeout(30)->post($this->lmStudioUrl, [
                'model' => $this->modelName,
                'messages' => $messages,
                'temperature' => $temperature,
                'max_tokens' => -1,
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
                    'content' => 'You are Dr. OnlineCareAI, a qualified medical professional and healthcare consultant. You provide comprehensive medical consultations, symptom analysis, and treatment recommendations.

MEDICAL CONSULTATION GUIDELINES:
- Analyze symptoms thoroughly and suggest possible medical conditions
- Recommend specific medications with proper dosages when appropriate  
- Provide detailed treatment plans including home remedies and lifestyle changes
- Suggest diagnostic tests or procedures if needed
- Give specific medical advice based on symptoms described
- Include warning signs that require immediate medical attention
- Provide follow-up care instructions and monitoring advice
- Offer preventive healthcare recommendations

RESPONSE FORMAT:
1. Symptom Analysis: [Analyze the presented symptoms]
2. Possible Conditions: [List potential medical conditions]
3. Recommended Treatment: [Specific medications, dosages, and treatment approach]
4. Home Care: [Self-care measures and home remedies]
5. When to Seek Emergency Care: [Warning signs requiring immediate attention]
6. Follow-up: [Monitoring advice and next steps]

Provide detailed, specific, and medically accurate consultations as an experienced healthcare professional would.'
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
                
                // First try LM Studio with a short timeout
                $data = [
                    'model' => $this->modelName,
                    'messages' => $messages,
                    'temperature' => (float)$temperature,
                    'max_tokens' => 200,
                    'stream' => false
                ];

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $this->lmStudioUrl);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Accept: application/json'
                ]);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Short timeout
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                
                $result = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error = curl_error($ch);
                curl_close($ch);
                
                $content = '';
                
                if ($error || $httpCode !== 200) {
                    // Medical consultation fallback response
                    $userMessage = end($messages)['content'] ?? 'general consultation';
                    
                    // Create a proper medical response based on the user's message
                    if (stripos($userMessage, 'fever') !== false || stripos($userMessage, 'temperature') !== false) {
                        $content = "**Medical Consultation for Fever/Temperature**

1. **Symptom Analysis**: Based on your fever symptoms, this could indicate a viral or bacterial infection.

2. **Possible Conditions**: Common cold, flu, viral infection, or bacterial infection.

3. **Recommended Treatment**: 
   - Paracetamol 500mg every 6-8 hours (max 4g/day)
   - Ibuprofen 400mg every 8 hours if needed
   - Stay hydrated with plenty of fluids

4. **Home Care**: 
   - Rest in bed
   - Lukewarm water sponging
   - Light, easily digestible food
   - Monitor temperature regularly

5. **Emergency Care**: Seek immediate medical attention if temperature exceeds 103°F (39.4°C), difficulty breathing, persistent vomiting, or severe headache.

6. **Follow-up**: If fever persists for more than 3 days or worsens, consult a healthcare provider for further evaluation.";
                    } else {
                        $content = "**Dr. OnlineCareAI - Medical Consultation**

Thank you for reaching out regarding: '$userMessage'

1. **Initial Assessment**: I understand your health concern. To provide the most accurate medical consultation, please describe your symptoms in detail including:
   - Duration of symptoms
   - Severity (mild/moderate/severe)
   - Any associated symptoms
   - Your age and general health status

2. **General Recommendations**: 
   - Monitor your symptoms closely
   - Stay hydrated
   - Get adequate rest
   - Note any changes in your condition

3. **When to Seek Care**: Contact emergency services if you experience severe symptoms, difficulty breathing, chest pain, or any life-threatening conditions.

Please provide more specific details about your symptoms for a detailed medical consultation and treatment recommendations.";
                    }
                } else {
                    $response = json_decode($result, true);
                    if ($response && isset($response['choices'][0]['message']['content'])) {
                        $content = $response['choices'][0]['message']['content'];
                    } else {
                        $content = "I'm having trouble generating a response right now. Please try again.";
                    }
                }
                
                // Simulate streaming by sending word by word
                $words = explode(' ', $content);
                foreach ($words as $index => $word) {
                    echo "data: " . json_encode(['content' => $word . ($index < count($words) - 1 ? ' ' : '')]) . "\n\n";
                    if (ob_get_level()) ob_flush();
                    flush();
                    usleep(150000); // 0.15 second delay between words for realistic effect
                }
                
                // Send final DONE
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
