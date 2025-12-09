<?php
// Get API URL for chatbot
$chatbot_api_url = getUrlPath('api/chatbot.php');
?>
<!-- Chatbot Widget -->
<div id="chatbotWidget" class="chatbot-widget">
    <!-- Chatbot Toggle Button -->
    <button id="chatbotToggle" class="chatbot-toggle-btn" aria-label="Open Chatbot">
        <i class="bi bi-chat-dots-fill"></i>
        <span class="chatbot-badge" id="chatbotBadge" style="display: none;">1</span>
    </button>

    <!-- Chatbot Container -->
    <div id="chatbotContainer" class="chatbot-container" style="display: none;">
        <!-- Chatbot Header -->
        <div class="chatbot-header">
            <div class="d-flex align-items-center">
                <div class="chatbot-avatar">
                    <i class="bi bi-robot"></i>
                </div>
                <div class="ms-2">
                    <h6 class="mb-0">Customer Support</h6>
                    <small class="text-muted">We're here to help!</small>
                </div>
            </div>
            <button id="chatbotMinimize" class="btn btn-sm btn-link text-white p-0">
                <i class="bi bi-dash-lg"></i>
            </button>
            <button id="chatbotClose" class="btn btn-sm btn-link text-white p-0">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <!-- Chatbot Messages Area -->
        <div id="chatbotMessages" class="chatbot-messages">
            <div class="chatbot-welcome">
                <div class="chatbot-message bot-message">
                    <div class="message-content">
                        <p>Hello! 👋 I'm your customer support assistant. How can I help you today?</p>
                        <div class="quick-actions mt-2">
                            <button class="btn btn-sm btn-outline-primary quick-action-btn" data-question="What is my order status?">
                                Order Status
                            </button>
                            <button class="btn btn-sm btn-outline-primary quick-action-btn" data-question="What is your return policy?">
                                Return Policy
                            </button>
                            <button class="btn btn-sm btn-outline-primary quick-action-btn" data-question="Show me products">
                                Browse Products
                            </button>
                        </div>
                    </div>
                    <div class="message-time">Just now</div>
                </div>
            </div>
        </div>

        <!-- Chatbot Input Area -->
        <div class="chatbot-input-area">
            <div class="input-group">
                <input type="text" 
                       id="chatbotInput" 
                       class="form-control" 
                       placeholder="Type your message..." 
                       autocomplete="off">
                <button id="chatbotSend" class="btn btn-primary" type="button">
                    <i class="bi bi-send-fill"></i>
                </button>
            </div>
            <small class="text-muted d-block mt-1">
                <i class="bi bi-info-circle"></i> Ask about orders, products, returns, or shipping
            </small>
        </div>
    </div>
</div>

<style>
/* Chatbot Widget Styles */
.chatbot-widget {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 1000;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
}

/* Toggle Button */
.chatbot-toggle-btn {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    color: white;
    font-size: 24px;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    transition: all 0.3s ease;
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
}

.chatbot-toggle-btn:hover {
    transform: scale(1.1);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
}

.chatbot-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: #dc3545;
    color: white;
    border-radius: 50%;
    width: 24px;
    height: 24px;
    font-size: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}

/* Chatbot Container */
.chatbot-container {
    position: absolute;
    bottom: 80px;
    right: 0;
    width: 380px;
    max-width: calc(100vw - 40px);
    height: 600px;
    max-height: calc(100vh - 100px);
    background: white;
    border-radius: 12px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    animation: slideUp 0.3s ease;
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Chatbot Header */
.chatbot-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 15px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.chatbot-avatar {
    width: 40px;
    height: 40px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}

.chatbot-header button {
    color: white;
    opacity: 0.8;
    transition: opacity 0.2s;
}

.chatbot-header button:hover {
    opacity: 1;
}

/* Messages Area */
.chatbot-messages {
    flex: 1;
    overflow-y: auto;
    padding: 20px;
    background: #f8f9fa;
}

.chatbot-messages::-webkit-scrollbar {
    width: 6px;
}

.chatbot-messages::-webkit-scrollbar-track {
    background: #f1f1f1;
}

.chatbot-messages::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 3px;
}

.chatbot-messages::-webkit-scrollbar-thumb:hover {
    background: #555;
}

.chatbot-message {
    margin-bottom: 15px;
    display: flex;
    flex-direction: column;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.user-message {
    align-items: flex-end;
}

.bot-message {
    align-items: flex-start;
}

.message-content {
    max-width: 75%;
    padding: 12px 16px;
    border-radius: 18px;
    word-wrap: break-word;
}

.user-message .message-content {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-bottom-right-radius: 4px;
}

.bot-message .message-content {
    background: white;
    color: #333;
    border: 1px solid #e0e0e0;
    border-bottom-left-radius: 4px;
}

.message-content p {
    margin: 0;
    line-height: 1.5;
}

.message-time {
    font-size: 11px;
    color: #999;
    margin-top: 4px;
    padding: 0 4px;
}

.quick-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 8px;
}

.quick-action-btn {
    font-size: 12px;
    padding: 4px 12px;
    border-radius: 12px;
}

/* Input Area */
.chatbot-input-area {
    padding: 15px;
    background: white;
    border-top: 1px solid #e0e0e0;
}

.chatbot-input-area .input-group {
    margin-bottom: 8px;
}

.chatbot-input-area input {
    border-radius: 20px;
    border: 1px solid #e0e0e0;
    padding: 10px 15px;
}

.chatbot-input-area input:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
}

.chatbot-input-area button {
    border-radius: 20px;
    padding: 10px 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
}

.chatbot-input-area button:hover {
    opacity: 0.9;
}

/* Typing Indicator */
.typing-indicator {
    display: flex;
    align-items: center;
    padding: 12px 16px;
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 18px;
    border-bottom-left-radius: 4px;
    max-width: 75px;
}

.typing-indicator span {
    height: 8px;
    width: 8px;
    background: #999;
    border-radius: 50%;
    display: inline-block;
    margin: 0 2px;
    animation: typing 1.4s infinite;
}

.typing-indicator span:nth-child(2) {
    animation-delay: 0.2s;
}

.typing-indicator span:nth-child(3) {
    animation-delay: 0.4s;
}

@keyframes typing {
    0%, 60%, 100% {
        transform: translateY(0);
    }
    30% {
        transform: translateY(-10px);
    }
}

/* Responsive */
@media (max-width: 768px) {
    .chatbot-container {
        width: calc(100vw - 20px);
        height: calc(100vh - 80px);
        bottom: 10px;
        right: 10px;
    }
    
    .chatbot-widget {
        bottom: 10px;
        right: 10px;
    }
}

/* Minimized State */
.chatbot-container.minimized {
    height: 60px;
    overflow: hidden;
}

.chatbot-container.minimized .chatbot-messages,
.chatbot-container.minimized .chatbot-input-area {
    display: none;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const chatbotToggle = document.getElementById('chatbotToggle');
    const chatbotContainer = document.getElementById('chatbotContainer');
    const chatbotClose = document.getElementById('chatbotClose');
    const chatbotMinimize = document.getElementById('chatbotMinimize');
    const chatbotSend = document.getElementById('chatbotSend');
    const chatbotInput = document.getElementById('chatbotInput');
    const chatbotMessages = document.getElementById('chatbotMessages');
    const quickActionBtns = document.querySelectorAll('.quick-action-btn');

    // Toggle chatbot
    chatbotToggle.addEventListener('click', function() {
        if (chatbotContainer.style.display === 'none') {
            chatbotContainer.style.display = 'flex';
            chatbotContainer.classList.remove('minimized');
            chatbotInput.focus();
        } else {
            chatbotContainer.style.display = 'none';
        }
    });

    // Close chatbot
    chatbotClose.addEventListener('click', function() {
        chatbotContainer.style.display = 'none';
    });

    // Minimize chatbot
    chatbotMinimize.addEventListener('click', function() {
        chatbotContainer.classList.toggle('minimized');
    });

    // Send message function
    function sendMessage(message) {
        if (!message.trim()) return;

        // Add user message
        addMessage(message, 'user');

        // Clear input
        chatbotInput.value = '';

        // Show typing indicator
        showTypingIndicator();

        // Send to API
        fetch('<?php echo $chatbot_api_url; ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                message: message
            })
        })
        .then(response => response.json())
        .then(data => {
            hideTypingIndicator();
            if (data.success) {
                addMessage(data.message, 'bot');
            } else {
                addMessage('Sorry, I encountered an error. Please try again.', 'bot');
            }
        })
        .catch(error => {
            hideTypingIndicator();
            addMessage('Sorry, I\'m having trouble connecting. Please try again later.', 'bot');
            console.error('Error:', error);
        });
    }

    // Add message to chat
    function addMessage(text, sender) {
        const messageDiv = document.createElement('div');
        messageDiv.className = 'chatbot-message ' + sender + '-message';

        const contentDiv = document.createElement('div');
        contentDiv.className = 'message-content';

        const p = document.createElement('p');
        p.textContent = text;
        contentDiv.appendChild(p);

        const timeDiv = document.createElement('div');
        timeDiv.className = 'message-time';
        const now = new Date();
        timeDiv.textContent = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

        messageDiv.appendChild(contentDiv);
        messageDiv.appendChild(timeDiv);

        chatbotMessages.appendChild(messageDiv);
        scrollToBottom();
    }

    // Show typing indicator
    function showTypingIndicator() {
        const typingDiv = document.createElement('div');
        typingDiv.className = 'chatbot-message bot-message';
        typingDiv.id = 'typingIndicator';
        typingDiv.innerHTML = '<div class="typing-indicator"><span></span><span></span><span></span></div>';
        chatbotMessages.appendChild(typingDiv);
        scrollToBottom();
    }

    // Hide typing indicator
    function hideTypingIndicator() {
        const typingIndicator = document.getElementById('typingIndicator');
        if (typingIndicator) {
            typingIndicator.remove();
        }
    }

    // Scroll to bottom
    function scrollToBottom() {
        chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
    }

    // Send button click
    chatbotSend.addEventListener('click', function() {
        sendMessage(chatbotInput.value);
    });

    // Enter key press
    chatbotInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            sendMessage(chatbotInput.value);
        }
    });

    // Quick action buttons
    quickActionBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            const question = this.getAttribute('data-question');
            sendMessage(question);
        });
    });
});
</script>

