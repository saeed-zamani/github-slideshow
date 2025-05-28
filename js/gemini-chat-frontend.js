document.addEventListener('DOMContentLoaded', function() {
    const floatingIcon = document.getElementById('gemini-chat-floating-icon');
    const chatBoxContainer = document.getElementById('gemini-chat-box-container');
    const closeBtn = document.getElementById('gemini-chat-close-btn');
    
    const messagesArea = document.getElementById('gemini-chat-messages-area');
    const messageInput = document.getElementById('gemini-chat-message-input');
    const sendBtn = document.getElementById('gemini-chat-send-btn');

    let conversationHistory = []; // To store chat history for Gemini context

    // Toggle chat box visibility
    if (floatingIcon && chatBoxContainer) {
        floatingIcon.addEventListener('click', function() {
            chatBoxContainer.classList.toggle('active');
            if (chatBoxContainer.classList.contains('active')) {
                messageInput.focus(); // Focus input when chat opens
                // Optional: Add initial greeting if history is empty
                if (messagesArea.children.length === 0) {
                     // appendMessage("Hi! How can I help you today?", 'ai-message');
                     // conversationHistory.push({ role: "model", parts: [{ text: "Hi! How can I help you today?" }] });
                }
            }
        });
    }

    if (closeBtn && chatBoxContainer) {
        closeBtn.addEventListener('click', function() {
            chatBoxContainer.classList.remove('active');
        });
    }

    // Helper function to append messages to the chat area
    function appendMessage(text, cssClass, isHtml = false) {
        const messageDiv = document.createElement('div');
        messageDiv.classList.add('chat-message', cssClass);
        if (isHtml) {
            messageDiv.innerHTML = text; // Use innerHTML if text contains HTML (e.g., for loading)
        } else {
            messageDiv.textContent = text;
        }
        messagesArea.appendChild(messageDiv);
        messagesArea.scrollTop = messagesArea.scrollHeight; // Auto-scroll
    }

    // Handle sending a message
    async function handleSendMessage() {
        const userMessageText = messageInput.value.trim();
        if (!userMessageText) return;

        appendMessage(userMessageText, 'user-message');
        conversationHistory.push({ role: "user", parts: [{ text: userMessageText }] });
        
        messageInput.value = '';
        messageInput.focus();
        sendBtn.disabled = true; // Disable send button

        // Add loading indicator
        appendMessage('Thinking<span>.</span><span>.</span><span>.</span>', 'loading-indicator', true);

        const formData = new URLSearchParams();
        formData.append('action', 'send_gemini_message');
        // Ensure geminiChatAjax is available (localized by WordPress)
        if (typeof geminiChatAjax === 'undefined') {
            appendMessage('Error: AJAX configuration is missing. Please reload the page.', 'error-message');
            console.error('geminiChatAjax is not defined. Ensure wp_localize_script is working.');
            sendBtn.disabled = false;
            const loadingIndicator = messagesArea.querySelector('.loading-indicator');
            if (loadingIndicator) loadingIndicator.remove();
            return;
        }
        formData.append('nonce', geminiChatAjax.nonce);
        formData.append('message', userMessageText);
        formData.append('history', JSON.stringify(conversationHistory));

        try {
            const response = await fetch(geminiChatAjax.ajax_url, {
                method: 'POST',
                body: formData,
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                }
            });

            // Remove loading indicator
            const loadingIndicator = messagesArea.querySelector('.loading-indicator');
            if (loadingIndicator) loadingIndicator.remove();

            const data = await response.json();

            if (data.success) {
                const aiReplyText = data.data.reply;
                appendMessage(aiReplyText, 'ai-message');
                conversationHistory.push({ role: "model", parts: [{ text: aiReplyText }] });
            } else {
                appendMessage('Error: ' + (data.data.message || 'Could not get response from AI.'), 'error-message');
                console.error('AJAX Error:', data.data.message);
                // Do not add AI error message to history
            }
        } catch (error) {
            // Remove loading indicator
            const loadingIndicator = messagesArea.querySelector('.loading-indicator');
            if (loadingIndicator) loadingIndicator.remove();
            appendMessage('Error: Failed to connect to the server. Please check your internet connection.', 'error-message');
            console.error('Fetch Error:', error);
        } finally {
            sendBtn.disabled = false; // Re-enable send button
            messageInput.focus();
        }
    }

    // Event listeners for sending messages
    if (sendBtn && messageInput) {
        sendBtn.addEventListener('click', handleSendMessage);
        messageInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) { // Send on Enter, allow Shift+Enter for newline
                e.preventDefault();
                handleSendMessage();
            }
        });
    }
});
