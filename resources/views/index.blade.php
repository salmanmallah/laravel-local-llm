
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>OnlineCareAI - Healthcare Chat Assistant</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="{{ asset('styles/style.css') }}" rel="stylesheet">0
</head>
<body>
    <div class="container-fluid h-100 p-0">
        <div class="row h-100 g-0">
            <!-- Left Sidebar -->
            <div class="col-md-4 col-lg-3 sidebar">
                <!-- Header -->
                <div class="sidebar-header">
                    <div class="d-flex align-items-center gap-3 mb-2">
                        <div class="ai-logo">
                            <span>AI</span>
                        </div>
                        <div>
                            <h1 class="brand-title">OnlineCareAI</h1>
                            <p class="brand-subtitle">The Future of Healthcare</p>
                        </div>
                    </div>
                </div>

                <!-- New Chat Button -->
                <div class="p-3 border-bottom">
                    <button class="btn btn-orange w-100" id="newChatBtn">
                        <i class="bi bi-plus-circle me-2"></i>
                        New Conversation
                    </button>
                </div>

                <!-- Conversations List -->
                <div class="p-3">
                    <h6 class="text-muted mb-3 text-uppercase fw-bold small">Recent Chats</h6>
                    <div id="conversationsList">
                        <!-- Conversations will be populated by JavaScript -->
                    </div>
                </div>
            </div>

            <!-- Main Chat Area -->
            <div class="col-md-8 col-lg-9 chat-area">
                <!-- Chat Header -->
                <div class="chat-header">
                    <div class="d-flex align-items-center gap-3">
                        <div class="ai-avatar">
                            <span>AI</span>
                        </div>
                        <div>
                            <span class="fw-bold text-dark" id="currentChatTitle">Healthcare Assistant</span>
                            <p class="text-muted small mb-0">AI Healthcare Assistant</p>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#settingsModal">
                            <i class="bi bi-gear"></i>
                        </button>
                    </div>
                </div>

                <!-- Messages Area -->
                <div class="messages-container" id="messagesContainer">
                    <div class="welcome-screen" id="welcomeScreen">
                        <div class="text-center">
                            <div class="welcome-avatar mx-auto mb-4">
                                <span>AI</span>
                            </div>
                            <h3 class="fw-bold text-dark mb-2">Welcome to OnlineCareAI</h3>
                            <p class="text-muted">I'm your AI healthcare assistant. Ask me anything about health, medical conditions, or wellness tips.</p>
                        </div>
                    </div>
                    <div id="messagesList">
                        <!-- Messages will be populated by JavaScript -->
                    </div>
                </div>

                <!-- Input Area -->
                <div class="input-area">
                    <form id="messageForm" class="mx-auto" style="max-width: 800px;">
                        <div class="input-group mb-3">
                            <input type="text" class="form-control message-input" id="messageInput" 
                                   placeholder="Ask your healthcare question..." autocomplete="off">
                            <button class="btn btn-orange" type="submit">
                                <i class="bi bi-send"></i>
                            </button>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="imageBtn">
                                    <i class="bi bi-image"></i>
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="fileBtn">
                                    <i class="bi bi-file-text"></i>
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#linkModal">
                                    <i class="bi bi-link-45deg"></i>
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm">
                                    <i class="bi bi-globe"></i>
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="cutBtn">
                                    <i class="bi bi-scissors"></i>
                                </button>
                            </div>
                            <small class="text-muted">Press Enter to send</small>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden File Inputs -->
    <input type="file" id="fileInput" class="d-none" accept=".txt,.pdf,.doc,.docx">
    <input type="file" id="imageInput" class="d-none" accept="image/*">

    <!-- Link Modal -->
    <div class="modal fade" id="linkModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Insert Link</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="linkUrl" class="form-label">Link URL</label>
                        <input type="url" class="form-control" id="linkUrl" placeholder="Enter link URL">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-orange" id="insertLinkBtn">Insert</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Settings Modal -->
    <div class="modal fade" id="settingsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <div class="d-flex align-items-center gap-2">
                            <div class="settings-ai-logo">
                                <span>AI</span>
                            </div>
                            AI Model Settings
                        </div>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-4">
                        <label class="form-label fw-bold">Select AI Model</label>
                        <div class="model-options">
                            <div class="form-check model-option">
                                <input class="form-check-input" type="radio" name="aiModel" id="deepseek" value="deepseek-r1" checked>
                                <label class="form-check-label" for="deepseek">
                                    <div class="fw-medium">DeepSeek R1 (Local)</div>
                                    <small class="text-muted">Local LM Studio model - Fast and private</small>
                                </label>
                            </div>
                            <div class="form-check model-option">
                                <input class="form-check-input" type="radio" name="aiModel" id="gpt35" value="gpt-3.5-turbo">
                                <label class="form-check-label" for="gpt35">
                                    <div class="fw-medium">GPT-3.5 Turbo</div>
                                    <small class="text-muted">Fast and efficient for general healthcare queries</small>
                                </label>
                            </div>
                            <div class="form-check model-option">
                                <input class="form-check-input" type="radio" name="aiModel" id="gpt4" value="gpt-4">
                                <label class="form-check-label" for="gpt4">
                                    <div class="fw-medium">GPT-4</div>
                                    <small class="text-muted">Advanced model for complex medical analysis</small>
                                </label>
                            </div>
                            <div class="form-check model-option">
                                <input class="form-check-input" type="radio" name="aiModel" id="claude" value="claude-v1">
                                <label class="form-check-label" for="claude">
                                    <div class="fw-medium">Claude v1</div>
                                    <small class="text-muted">Specialized in healthcare conversations</small>
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label fw-bold mb-0">Response Creativity</label>
                            <span class="text-orange fw-medium" id="temperatureValue">0.7</span>
                        </div>
                        <input type="range" class="form-range" id="temperatureSlider" min="0" max="1" step="0.1" value="0.7">
                        <div class="d-flex justify-content-between">
                            <small class="text-muted">More Focused</small>
                            <small class="text-muted">More Creative</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JavaScript -->
    <script src="script.js"></script>
</body>
</html>