// GoSocially Dashboard JavaScript
// Real-time messaging and interactive features

document.addEventListener('DOMContentLoaded', function() {
    // Global variables
    let currentUserId = null;
    let currentConversationId = null;
    let messagePollingInterval = null;
    let invitePollingInterval = null;

    // Initialize dashboard
    initDashboard();

    function initDashboard() {
        // Get current user ID from page
        const userInfo = document.querySelector('.user-info');
        if (userInfo) {
            // User ID will be embedded in a data attribute by PHP
            currentUserId = document.body.dataset.userId;
        }

        // Setup event listeners
        setupEventListeners();

        // Start polling for updates if in a conversation
        if (window.location.search.includes('conversation=')) {
            startMessagePolling();
        }

        // Load invite codes
        loadInviteCodes();

        // Setup auto-refresh for invite codes
        startInvitePolling();
    }

    function setupEventListeners() {
        // Conversation clicks
        const conversationItems = document.querySelectorAll('.conversation-item');
        conversationItems.forEach(item => {
            item.addEventListener('click', function() {
                const userId = this.dataset.userId;
                openConversation(userId);
            });
        });

        // User directory clicks
        const userItems = document.querySelectorAll('.user-item');
        userItems.forEach(item => {
            item.addEventListener('click', function() {
                const userId = this.dataset.userId;
                openConversation(userId);
            });
        });

        // Message form submission
        const messageForm = document.getElementById('message-form');
        if (messageForm) {
            messageForm.addEventListener('submit', handleSendMessage);
        }

        // Generate invite buttons
        const generateInviteBtn = document.getElementById('generate-invite-btn');
        if (generateInviteBtn) {
            generateInviteBtn.addEventListener('click', handleGenerateInvite);
        }

        const generateInviteWelcomeBtn = document.getElementById('generate-invite-welcome-btn');
        if (generateInviteWelcomeBtn) {
            generateInviteWelcomeBtn.addEventListener('click', handleGenerateInvite);
        }

        // Follow buttons
        const followButtons = document.querySelectorAll('.follow-btn');
        followButtons.forEach(btn => {
            btn.addEventListener('click', handleFollowAction);
        });

        // Prevent user item clicks when clicking follow buttons
        const userItemsForStop = document.querySelectorAll('.user-item');
        userItemsForStop.forEach(item => {
            const followBtn = item.querySelector('.follow-btn');
            if (followBtn) {
                followBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                });
            }
        });

        // Enter key to send message
        const messageInput = document.getElementById('message-input');
        if (messageInput) {
            messageInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    const form = document.getElementById('message-form');
                    if (form) {
                        form.dispatchEvent(new Event('submit'));
                    }
                }
            });

            // Auto-resize textarea
            messageInput.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = Math.min(this.scrollHeight, 120) + 'px';
            });
        }
    }

    function openConversation(userId) {
        if (userId === currentUserId) {
            showMessage('You cannot message yourself!', 'error');
            return;
        }

        // Redirect to conversation
        window.location.href = `dashboard.php?conversation=${userId}`;
    }

    async function handleFollowAction(e) {
        e.preventDefault();
        e.stopPropagation();

        const btn = e.target;
        const userId = btn.dataset.userId;
        const csrfToken = btn.dataset.csrfToken;
        const originalText = btn.textContent;
        const isFollowing = btn.classList.contains('following');

        // Disable button and show loading state
        btn.disabled = true;
        btn.textContent = 'Processing...';

        try {
            const formData = new FormData();
            formData.append('action', isFollowing ? 'unfollow' : 'follow');
            formData.append('user_id', userId);
            formData.append('csrf_token', csrfToken);

            const response = await fetch('follow.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                if (isFollowing) {
                    // Unfollowed
                    btn.textContent = 'Follow';
                    btn.classList.remove('following');
                    showMessage(result.message || 'User unfollowed', 'success');

                    // Update mutual indicator if it exists
                    const mutualIndicator = btn.parentElement.querySelector('.mutual-indicator');
                    if (mutualIndicator) {
                        mutualIndicator.remove();
                    }
                } else {
                    // Followed
                    btn.textContent = 'Following';
                    btn.classList.add('following');
                    showMessage(result.message || 'User followed', 'success');
                }

                // Refresh presence indicators and user data
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                showMessage(result.error || 'Failed to update follow status', 'error');
                btn.textContent = originalText;
            }
        } catch (error) {
            console.error('Follow action error:', error);
            showMessage('Network error. Please try again.', 'error');
            btn.textContent = originalText;
        } finally {
            btn.disabled = false;
        }
    }

    async function handleSendMessage(e) {
        e.preventDefault();

        const form = e.target;
        const submitBtn = form.querySelector('.send-btn');
        const messageInput = document.getElementById('message-input');
        const messageText = messageInput.value.trim();

        if (!messageText) {
            return;
        }

        // Disable submit button
        submitBtn.disabled = true;
        submitBtn.textContent = 'Sending...';

        try {
            const formData = new FormData(form);
            const response = await fetch('api/messages.php?action=send', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                // Clear input
                messageInput.value = '';
                messageInput.style.height = 'auto';

                // Add message to UI immediately
                addMessageToUI({
                    sender_id: currentUserId,
                    message_text: messageText,
                    sent_at: result.timestamp,
                    is_read: false
                }, true);

                // Refresh messages after a short delay
                setTimeout(() => loadMessages(), 500);
            } else {
                showMessage(result.error || 'Failed to send message', 'error');
            }
        } catch (error) {
            console.error('Send message error:', error);
            showMessage('Network error. Please try again.', 'error');
        } finally {
            // Re-enable submit button
            submitBtn.disabled = false;
            submitBtn.textContent = 'Send';
            messageInput.focus();
        }
    }

    async function loadMessages() {
        if (!currentConversationId) {
            const urlParams = new URLSearchParams(window.location.search);
            currentConversationId = urlParams.get('conversation');
        }

        if (!currentConversationId) return;

        try {
            const response = await fetch(`api/messages.php?action=get_conversation&user_id=${currentConversationId}`);
            const result = await response.json();

            if (result.success) {
                updateMessagesUI(result.messages);
                updateUnreadCount(result.unread_count);
            }
        } catch (error) {
            console.error('Load messages error:', error);
        }
    }

    function addMessageToUI(message, isSent = false) {
        const messagesContainer = document.getElementById('messages-container');
        if (!messagesContainer) return;

        // Remove "no messages" message if it exists
        const noMessagesMsg = messagesContainer.querySelector('.no-messages');
        if (noMessagesMsg) {
            noMessagesMsg.remove();
        }

        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${isSent ? 'sent' : 'received'}`;

        const messageContent = document.createElement('div');
        messageContent.className = 'message-content';

        const messageText = document.createElement('div');
        messageText.className = 'message-text';
        messageText.textContent = message.message_text;

        const messageTime = document.createElement('div');
        messageTime.className = 'message-time';
        messageTime.textContent = formatTime(message.sent_at);

        messageContent.appendChild(messageText);
        messageContent.appendChild(messageTime);

        if (isSent && message.is_read) {
            const readIndicator = document.createElement('div');
            readIndicator.className = 'message-read';
            readIndicator.textContent = '✓ Read';
            messageContent.appendChild(readIndicator);
        }

        messageDiv.appendChild(messageContent);
        messagesContainer.appendChild(messageDiv);

        // Scroll to bottom
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    function updateMessagesUI(messages) {
        const messagesContainer = document.getElementById('messages-container');
        if (!messagesContainer) return;

        // Clear existing messages
        messagesContainer.innerHTML = '';

        if (messages.length === 0) {
            messagesContainer.innerHTML = '<div class="no-messages">No messages yet. Start the conversation!</div>';
            return;
        }

        messages.forEach(message => {
            const isSent = message.sender_id == currentUserId;
            addMessageToUI(message, isSent);
        });

        // Scroll to bottom
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    function updateUnreadCount(count) {
        const unreadBadge = document.querySelector('.unread-badge');
        if (unreadBadge) {
            if (count > 0) {
                unreadBadge.textContent = count;
                unreadBadge.style.display = 'inline-block';
            } else {
                unreadBadge.style.display = 'none';
            }
        }
    }

    async function handleGenerateInvite(event) {
        // Accept event param and prefer currentTarget to get the button element
        const btn = event.currentTarget || event.target;
        const originalText = btn.textContent;

        btn.disabled = true;
        btn.textContent = 'Generating...';

        try {
            const response = await fetch('api/invites.php?action=generate', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `csrf_token=${encodeURIComponent(document.querySelector('meta[name="csrf-token"]')?.content || '')}`
            });

            const result = await response.json();

            if (result.success) {
                showMessage('Invite code generated successfully!', 'success');
                loadInviteCodes(); // Refresh the invite codes list
            } else {
                showMessage(result.error || 'Failed to generate invite code', 'error');
            }
        } catch (error) {
            console.error('Generate invite error:', error);
            showMessage('Network error. Please try again.', 'error');
        } finally {
            btn.disabled = false;
            btn.textContent = originalText;
        }
    }

    async function loadInviteCodes() {
        try {
            const response = await fetch('api/invites.php?action=list&limit=5');
            const result = await response.json();

            if (result.success) {
                updateInviteCodesUI(result.invite_codes);
            }
        } catch (error) {
            console.error('Load invite codes error:', error);
        }
    }

    function updateInviteCodesUI(inviteCodes) {
        const inviteCodesList = document.getElementById('invite-codes-list');
        if (!inviteCodesList) return;

        if (inviteCodes.length === 0) {
            inviteCodesList.innerHTML = '<p style="color: #6c757d; font-size: 14px;">No invite codes generated yet.</p>';
            return;
        }

        let html = '';
        inviteCodes.forEach(code => {
            const status = code.is_used ? 'Used' :
                          (code.expires_at && new Date(code.expires_at) < new Date()) ? 'Expired' : 'Active';
            const statusClass = code.is_used ? 'text-warning' :
                               (code.expires_at && new Date(code.expires_at) < new Date()) ? 'text-danger' : 'text-success';

            html += `
                <div class="invite-code-item">
                    <div class="code">${code.code}</div>
                    <div class="info">
                        Status: <span class="${statusClass}">${status}</span><br>
                        Created: ${formatDateTime(code.created_at)}<br>
                        ${code.used_by_username ? 'Used by: ' + code.used_by_username : ''}
                        ${code.expires_at ? '<br>Expires: ' + formatDateTime(code.expires_at) : ''}
                        <button class="copy-btn" onclick="copyToClipboard('${code.code}')">Copy</button>
                    </div>
                </div>
            `;
        });

        inviteCodesList.innerHTML = html;
    }

    function startMessagePolling() {
        // Clear existing interval
        if (messagePollingInterval) {
            clearInterval(messagePollingInterval);
        }

        // Poll for new messages every 5 seconds
        messagePollingInterval = setInterval(() => {
            loadMessages();
        }, 5000);
    }

    function startInvitePolling() {
        // Clear existing interval
        if (invitePollingInterval) {
            clearInterval(invitePollingInterval);
        }

        // Poll for invite codes every 30 seconds
        invitePollingInterval = setInterval(() => {
            loadInviteCodes();
        }, 30000);
    }

    function showMessage(message, type = 'info') {
        // Remove existing messages
        const existingMessages = document.querySelectorAll('.error-message, .success-message');
        existingMessages.forEach(msg => msg.remove());

        const messageDiv = document.createElement('div');
        messageDiv.className = `${type}-message`;
        messageDiv.textContent = message;
        messageDiv.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            padding: 15px 20px;
            border-radius: 5px;
            max-width: 300px;
            animation: slideIn 0.3s ease;
        `;

        if (type === 'error') {
            messageDiv.style.background = '#ff6b6b';
            messageDiv.style.color = 'white';
        } else if (type === 'success') {
            messageDiv.style.background = '#51cf66';
            messageDiv.style.color = 'white';
        } else {
            messageDiv.style.background = '#00C6F0';
            messageDiv.style.color = 'white';
        }

        document.body.appendChild(messageDiv);

        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (messageDiv.parentNode) {
                messageDiv.parentNode.removeChild(messageDiv);
            }
        }, 5000);
    }

    // Utility functions
    function formatTime(dateString) {
        const date = new Date(dateString);
        return date.toLocaleTimeString('en-US', {
            hour: 'numeric',
            minute: '2-digit',
            hour12: true
        });
    }

    function formatDateTime(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diffMs = now - date;
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMs / 3600000);
        const diffDays = Math.floor(diffMs / 86400000);

        if (diffMins < 1) {
            return 'Just now';
        } else if (diffMins < 60) {
            return `${diffMins} minute${diffMins > 1 ? 's' : ''} ago`;
        } else if (diffHours < 24) {
            return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
        } else if (diffDays < 7) {
            return `${diffDays} day${diffDays > 1 ? 's' : ''} ago`;
        } else {
            return date.toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric',
                year: date.getFullYear() !== now.getFullYear() ? 'numeric' : undefined
            });
        }
    }

    // Make copyToClipboard available globally
    window.copyToClipboard = function(text) {
        navigator.clipboard.writeText(text).then(() => {
            showMessage('Invite code copied to clipboard!', 'success');
        }).catch(err => {
            console.error('Copy failed:', err);
            showMessage('Failed to copy to clipboard', 'error');
        });
    };

    // Cleanup on page unload
    window.addEventListener('beforeunload', function() {
        if (messagePollingInterval) {
            clearInterval(messagePollingInterval);
        }
        if (invitePollingInterval) {
            clearInterval(invitePollingInterval);
        }
    });

    // Add CSS animation
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
    `;
    document.head.appendChild(style);
});