class OnlineCareChat {
    constructor() {
        this.conversationHistory = [];
        this.currentConversationId = null;
        this.isLoading = false;
        this.currentModel = 'deepseek-r1';
        this.temperature = 0.7;
        
        this.initializeElements();
        this.attachEventListeners();
        this.checkModelStatus();
    }

    initializeElements() {
        this.messageForm = document.getElementById('messageForm');
        this.messageInput = document.getElementById('messageInput');
        this.messagesContainer = document.getElementById('messagesContainer');
        this.messagesList = document.getElementById('messagesList');
        this.welcomeScreen = document.getElementById('welcomeScreen');
        this.newChatBtn = document.getElementById('newChatBtn');
        this.temperatureSlider = document.getElementById('temperatureSlider');
        this.temperatureValue = document.getElementById('temperatureValue');
    }

    attachEventListeners() {
        // Message form submission
        this.messageForm.addEventListener('submit', (e) => {
            e.preventDefault();
            this.sendMessage();
        });

        // New chat button
        this.newChatBtn.addEventListener('click', () => {
            this.startNewConversation();
        });

        // Temperature slider
        this.temperatureSlider.addEventListener('input', (e) => {
            this.temperature = parseFloat(e.target.value);
            this.temperatureValue.textContent = this.temperature.toFixed(1);
        });

        // Model selection
        document.querySelectorAll('input[name="aiModel"]').forEach(radio => {
            radio.addEventListener('change', (e) => {
                this.currentModel = e.target.value;
            });
        });

        // Enter key submission
        this.messageInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        });
    }

    async checkModelStatus() {
        try {
            const response = await fetch('/api/chat/model-status');
            const data = await response.json();
            
            if (data.success) {
                this.showConnectionStatus('connected', 'LM Studio model is connected and ready');
            } else {
                this.showConnectionStatus('disconnected', 'Failed to connect to LM Studio');
            }
        } catch (error) {
            this.showConnectionStatus('disconnected', 'LM Studio is not accessible');
        }
    }

    showConnectionStatus(status, message) {
        const statusElement = document.createElement('div');
        statusElement.className = `alert alert-${status === 'connected' ? 'success' : 'warning'} alert-dismissible fade show`;
        statusElement.innerHTML = `
            <i class="bi bi-${status === 'connected' ? 'check-circle' : 'exclamation-triangle'}"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        // Insert at the top of the chat area
        const chatHeader = document.querySelector('.chat-header');
        chatHeader.after(statusElement);
        
        // Auto-dismiss after 5 seconds if connected
        if (status === 'connected') {
            setTimeout(() => {
                statusElement.remove();
            }, 5000);
        }
    }

    async sendMessage() {
        const message = this.messageInput.value.trim();
        if (!message || this.isLoading) return;

        this.isLoading = true;
        this.hideWelcomeScreen();
        
        // Add user message to UI
        this.addMessageToUI('user', message);
        this.messageInput.value = '';
        this.messageInput.disabled = true;

        // Show typing indicator
        const typingIndicator = this.showTypingIndicator();

        try {
            const response = await fetch('/api/chat/send', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify({
                    message: message,
                    temperature: this.temperature,
                    conversation_history: this.conversationHistory
                })
            });

            const data = await response.json();

            if (data.success) {
                // Add user message to history
                this.conversationHistory.push({
                    role: 'user',
                    content: message
                });

                // Add AI response to history (only final answer for history)
                this.conversationHistory.push({
                    role: 'assistant',
                    content: data.response
                });

                // Remove typing indicator and add thinking process if available
                if (data.thinking_process) {
                    // Replace typing with thinking process
                    this.replaceTypingWithThinking(typingIndicator, data.thinking_process);
                    
                    // Wait a moment then show final answer
                    setTimeout(() => {
                        this.addMessageToUI('assistant', data.response, data.thinking_process);
                    }, 1000);
                } else {
                    // No thinking process, just show response
                    typingIndicator.remove();
                    this.addMessageToUI('assistant', data.response);
                }

                // Show token usage if available
                if (data.tokens_used) {
                    console.log('Tokens used:', data.tokens_used);
                }
            } else {
                typingIndicator.remove();
                this.addMessageToUI('error', `Error: ${data.error}`);
            }
        } catch (error) {
            typingIndicator.remove();
            this.addMessageToUI('error', 'Failed to connect to the AI model. Please check if LM Studio is running.');
        } finally {
            this.isLoading = false;
            this.messageInput.disabled = false;
            this.messageInput.focus();
        }
    }

    replaceTypingWithThinking(typingIndicator, thinkingText) {
        // Create thinking indicator
        const thinkingDiv = document.createElement('div');
        thinkingDiv.className = 'message assistant-message thinking-message';
        thinkingDiv.innerHTML = `
            <div class="message-avatar ai-avatar">
                <span>AI</span>
            </div>
            <div class="message-content thinking-content">
                <div class="thinking-header">
                    <span class="thinking-label">Thinking...</span>
                    <div class="thinking-spinner">
                        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                    </div>
                </div>
                <div class="thinking-text" style="display: none;">
                    ${this.formatMessage(thinkingText)}
                </div>
            </div>
        `;
        
        // Replace typing indicator
        typingIndicator.replaceWith(thinkingDiv);
        
        // After a short delay, collapse to "Thought for X seconds" style
        setTimeout(() => {
            const thinkingHeader = thinkingDiv.querySelector('.thinking-header');
            const thinkingContent = thinkingDiv.querySelector('.thinking-text');
            
            thinkingHeader.innerHTML = `
                <span class="thinking-label collapsed" onclick="toggleThinking(this)">
                    <i class="bi bi-chevron-right"></i>
                    Thought for a few seconds
                </span>
            `;
            thinkingHeader.style.cursor = 'pointer';
            
            // Add click handler to toggle thinking visibility
            thinkingHeader.onclick = () => {
                const icon = thinkingHeader.querySelector('i');
                if (thinkingContent.style.display === 'none') {
                    thinkingContent.style.display = 'block';
                    icon.className = 'bi bi-chevron-down';
                } else {
                    thinkingContent.style.display = 'none';
                    icon.className = 'bi bi-chevron-right';
                }
            };
        }, 2000);
    }

    addMessageToUI(role, content, thinkingProcess = null) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${role}-message`;
        
        const timestamp = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        
        if (role === 'user') {
            messageDiv.innerHTML = `
                <div class="message-content user-content">
                    <div class="message-text">${this.formatMessage(content)}</div>
                    <div class="message-time">${timestamp}</div>
                </div>
                <div class="message-avatar user-avatar">
                    <i class="bi bi-person-fill"></i>
                </div>
            `;
        } else if (role === 'assistant') {
            messageDiv.innerHTML = `
                <div class="message-avatar ai-avatar">
                    <span>AI</span>
                </div>
                <div class="message-content ai-content">
                    <div class="message-text">${this.formatMessage(content)}</div>
                    <div class="message-time">${timestamp}</div>
                    <div class="message-actions">
                        <button class="btn btn-sm btn-outline-secondary" onclick="copyToClipboard(this)" data-content="${content.replace(/"/g, '&quot;')}">
                            <i class="bi bi-copy"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-secondary" onclick="regenerateResponse()">
                            <i class="bi bi-arrow-clockwise"></i>
                        </button>
                    </div>
                </div>
            `;
        } else if (role === 'error') {
            messageDiv.innerHTML = `
                <div class="message-avatar ai-avatar bg-danger">
                    <i class="bi bi-exclamation-triangle"></i>
                </div>
                <div class="message-content error-content">
                    <div class="message-text text-danger">${content}</div>
                    <div class="message-time">${timestamp}</div>
                </div>
            `;
        }

        this.messagesList.appendChild(messageDiv);
        this.scrollToBottom();
    }

    showTypingIndicator() {
        const typingDiv = document.createElement('div');
        typingDiv.className = 'message assistant-message typing-indicator';
        typingDiv.innerHTML = `
            <div class="message-avatar ai-avatar">
                <span>AI</span>
            </div>
            <div class="message-content ai-content">
                <div class="typing-dots">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </div>
        `;
        this.messagesList.appendChild(typingDiv);
        this.scrollToBottom();
        return typingDiv;
    }

    formatMessage(text) {
        // Basic markdown-like formatting
        return text
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
            .replace(/\*(.*?)\*/g, '<em>$1</em>')
            .replace(/\n/g, '<br>')
            .replace(/`(.*?)`/g, '<code>$1</code>');
    }

    hideWelcomeScreen() {
        if (this.welcomeScreen) {
            this.welcomeScreen.style.display = 'none';
        }
    }

    startNewConversation() {
        this.conversationHistory = [];
        this.currentConversationId = Date.now();
        this.messagesList.innerHTML = '';
        if (this.welcomeScreen) {
            this.welcomeScreen.style.display = 'block';
        }
    }

    scrollToBottom() {
        this.messagesContainer.scrollTop = this.messagesContainer.scrollHeight;
    }
}

// Utility functions
function copyToClipboard(button) {
    const content = button.getAttribute('data-content');
    navigator.clipboard.writeText(content).then(() => {
        const icon = button.querySelector('i');
        icon.className = 'bi bi-check';
        setTimeout(() => {
            icon.className = 'bi bi-copy';
        }, 2000);
    });
}

function regenerateResponse() {
    // Implement regenerate functionality
    console.log('Regenerate response requested');
}

// Initialize chat when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.chatApp = new OnlineCareChat();
});

// Add some CSS for typing indicator
const style = document.createElement('style');
style.textContent = `
    .typing-dots {
        display: flex;
        align-items: center;
        gap: 4px;
        padding: 8px 12px;
    }
    
    .typing-dots span {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background-color: #6c757d;
        animation: typing 1.4s infinite ease-in-out;
    }
    
    .typing-dots span:nth-child(1) { animation-delay: -0.32s; }
    .typing-dots span:nth-child(2) { animation-delay: -0.16s; }
    
    @keyframes typing {
        0%, 80%, 100% { opacity: 0.3; transform: scale(0.8); }
        40% { opacity: 1; transform: scale(1); }
    }
    
    .message {
        display: flex;
        margin-bottom: 1rem;
        gap: 0.75rem;
        align-items: flex-start;
    }
    
    .user-message {
        flex-direction: row-reverse;
    }
    
    .message-content {
        max-width: 70%;
        padding: 0.75rem 1rem;
        border-radius: 1rem;
        position: relative;
    }
    
    .user-content {
        background-color: #007bff;
        color: white;
        border-bottom-right-radius: 0.25rem;
    }
    
    .ai-content {
        background-color: #f8f9fa;
        border: 1px solid #dee2e6;
        border-bottom-left-radius: 0.25rem;
    }
    
    .error-content {
        background-color: #f8d7da;
        border: 1px solid #f5c6cb;
        border-bottom-left-radius: 0.25rem;
    }
    
    .message-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.875rem;
        font-weight: 600;
        flex-shrink: 0;
    }
    
    .user-avatar {
        background-color: #007bff;
        color: white;
    }
    
    .ai-avatar {
        background-color: #ff6b35;
        color: white;
    }
    
    .message-time {
        font-size: 0.75rem;
        color: #6c757d;
        margin-top: 0.25rem;
    }
    
    .user-content .message-time {
        color: rgba(255, 255, 255, 0.8);
    }
    
    .message-actions {
        margin-top: 0.5rem;
        display: flex;
        gap: 0.25rem;
    }
    
    .message-actions .btn {
        padding: 0.25rem 0.5rem;
        font-size: 0.75rem;
    }
`;
document.head.appendChild(style);
