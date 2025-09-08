$(document).ready(function() {
    let currentReceiverId = null;
    let messageInterval = null;
    
    // Select user to chat with
    window.selectUser = function(userId, username) {
        currentReceiverId = userId;
        $('#receiver-id').val(userId);
        $('#chat-username').text(username);
        $('#chat-status').text('Online').addClass('online');
        
        // Update UI
        $('.user-item').removeClass('active');
        $(`.user-item[data-user-id="${userId}"]`).addClass('active');
        
        // Show message input
        $('#message-input-container').show();
        
        // Load messages
        loadMessages(userId);
        
        // Start auto-refresh messages
        if (messageInterval) clearInterval(messageInterval);
        messageInterval = setInterval(() => loadMessages(userId), 2000);
    };
    
    // Load messages between current user and selected user
    function loadMessages(userId) {
        $.ajax({
            url: 'get_messages.php',
            method: 'POST',
            data: { user_id: userId },
            dataType: 'json',
            success: function(messages) {
                displayMessages(messages);
            }
        });
    }
    
    // Display messages in chat area
    function displayMessages(messages) {
        const container = $('#messages-container');
        container.empty();
        
        if (messages.length === 0) {
            container.html(`
                <div class="welcome-message">
                    <i class="fas fa-comments"></i>
                    <h3>Belum ada pesan</h3>
                    <p>Mulai percakapan dengan mengirim pesan</p>
                </div>
            `);
            return;
        }
        
        messages.forEach(function(message) {
            const messageClass = message.is_sent ? 'sent' : 'received';
            let messageContent = '';
            
            // Handle different message types
            switch(message.message_type) {
                case 'text':
                    messageContent = `<div class="message-text">${escapeHtml(message.message_content)}</div>`;
                    break;
                case 'image':
                    messageContent = `
                        <div class="message-text">${message.message_content || 'Gambar'}</div>
                        <div class="message-media">
                            <img src="${message.file_path}" alt="Image" onclick="openModal('${message.file_path}')">
                        </div>
                    `;
                    break;
                case 'video':
                    messageContent = `
                        <div class="message-text">${message.message_content || 'Video'}</div>
                        <div class="message-media">
                            <video controls>
                                <source src="${message.file_path}" type="video/mp4">
                                Browser Anda tidak mendukung video.
                            </video>
                        </div>
                    `;
                    break;
                case 'audio':
                    messageContent = `
                        <div class="message-text">${message.message_content || 'Audio'}</div>
                        <div class="message-media">
                            <audio controls>
                                <source src="${message.file_path}" type="audio/mpeg">
                                Browser Anda tidak mendukung audio.
                            </audio>
                        </div>
                    `;
                    break;
            }
            
            const messageHtml = `
                <div class="message ${messageClass}">
                    ${messageContent}
                    <div class="message-time">${formatTime(message.created_at)}</div>
                </div>
            `;
            
            container.append(messageHtml);
        });
        
        // Scroll to bottom
        container.scrollTop(container[0].scrollHeight);
    }
    
    // Handle file upload buttons
    $('#image-btn').click(function() {
        resetUploadButtons();
        $(this).addClass('active');
        $('#file-input').attr('accept', 'image/*').click();
    });
    
    $('#video-btn').click(function() {
        resetUploadButtons();
        $(this).addClass('active');
        $('#file-input').attr('accept', 'video/*').click();
    });
    
    $('#audio-btn').click(function() {
        resetUploadButtons();
        $(this).addClass('active');
        $('#file-input').attr('accept', 'audio/*').click();
    });
    
    function resetUploadButtons() {
        $('.upload-btn').removeClass('active');
    }
    
    // Handle file selection
    $('#file-input').change(function() {
        const file = this.files[0];
        if (file) {
            const fileName = file.name;
            $('#message-text').val(fileName).attr('placeholder', 'File dipilih: ' + fileName);
        }
    });
    
    // Handle message form submission
    $('#message-form').submit(function(e) {
        e.preventDefault();
        
        if (!currentReceiverId) {
            alert('Pilih pengguna terlebih dahulu!');
            return;
        }
        
        const messageText = $('#message-text').val().trim();
        const fileInput = $('#file-input')[0];
        
        if (!messageText && !fileInput.files.length) {
            return;
        }
        
        // Create FormData for file upload
        const formData = new FormData(this);
        
        // Send message
        $.ajax({
            url: 'send_message.php',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Clear form
                    $('#message-text').val('').attr('placeholder', 'Ketik pesan...');
                    $('#file-input').val('');
                    resetUploadButtons();
                    
                    // Reload messages immediately
                    loadMessages(currentReceiverId);
                } else {
                    alert('Gagal mengirim pesan: ' + response.message);
                }
            },
            error: function() {
                alert('Terjadi kesalahan saat mengirim pesan!');
            }
        });
    });
    
    // Handle Enter key for sending messages
    $('#message-text').keypress(function(e) {
        if (e.which === 13 && !e.shiftKey) {
            e.preventDefault();
            $('#message-form').submit();
        }
    });
    
    // Utility functions
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function formatTime(timestamp) {
        const date = new Date(timestamp);
        const now = new Date();
        const diff = now.getDate() - date.getDate();
        
        if (diff === 0) {
            return date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
        } else if (diff === 1) {
            return 'Kemarin ' + date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
        } else {
            return date.toLocaleDateString('id-ID') + ' ' + date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
        }
    }
    
    // Modal for viewing images
    window.openModal = function(imageSrc) {
        const modal = $(`
            <div class="modal-overlay" onclick="closeModal()">
                <div class="modal-content" onclick="event.stopPropagation()">
                    <span class="modal-close" onclick="closeModal()">&times;</span>
                    <img src="${imageSrc}" alt="Full Image" style="max-width: 100%; max-height: 90vh;">
                </div>
            </div>
        `);
        
        // Add modal styles if not exists
        if (!$('#modal-styles').length) {
            $('head').append(`
                <style id="modal-styles">
                    .modal-overlay {
                        position: fixed;
                        top: 0;
                        left: 0;
                        width: 100%;
                        height: 100%;
                        background: rgba(0,0,0,0.8);
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        z-index: 1000;
                    }
                    .modal-content {
                        position: relative;
                        background: white;
                        padding: 20px;
                        border-radius: 10px;
                        max-width: 90%;
                        max-height: 90%;
                    }
                    .modal-close {
                        position: absolute;
                        top: 10px;
                        right: 15px;
                        font-size: 24px;
                        font-weight: bold;
                        cursor: pointer;
                        color: #aaa;
                    }
                    .modal-close:hover {
                        color: #000;
                    }
                </style>
            `);
        }
        
        $('body').append(modal);
    };
    
    window.closeModal = function() {
        $('.modal-overlay').remove();
    };
    
    // Update user last seen periodically
    setInterval(function() {
        $.ajax({
            url: 'update_last_seen.php',
            method: 'POST'
        });
    }, 30000); // Update every 30 seconds
    // Toggle sidebar untuk mobile
    window.toggleSidebar = function() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        sidebar.classList.toggle('show');
        overlay.classList.toggle('show');
    };
});