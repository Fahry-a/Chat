/**
 * Utility Functions for ChatApp
 */

// DOM utilities
const $ = (selector) => document.querySelector(selector);
const $$ = (selector) => document.querySelectorAll(selector);

// Show/hide elements
function show(element) {
    if (typeof element === 'string') {
        element = $(element);
    }
    if (element) {
        element.style.display = 'block';
    }
}

function hide(element) {
    if (typeof element === 'string') {
        element = $(element);
    }
    if (element) {
        element.style.display = 'none';
    }
}

function toggle(element) {
    if (typeof element === 'string') {
        element = $(element);
    }
    if (element) {
        element.style.display = element.style.display === 'none' ? 'block' : 'none';
    }
}

// Format file size
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// Format timestamp
function formatTime(timestamp) {
    const date = new Date(timestamp);
    const now = new Date();
    const diff = now - date;
    const minutes = Math.floor(diff / 60000);
    const hours = Math.floor(diff / 3600000);
    const days = Math.floor(diff / 86400000);

    if (minutes < 1) return 'Just now';
    if (minutes < 60) return `${minutes}m ago`;
    if (hours < 24) return `${hours}h ago`;
    if (days < 7) return `${days}d ago`;
    
    return date.toLocaleDateString();
}

// Format time for message display
function formatMessageTime(timestamp) {
    const date = new Date(timestamp);
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

// Escape HTML to prevent XSS
function escapeHtml(unsafe) {
    return unsafe
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

// Parse URLs and make them clickable
function linkifyText(text) {
    const urlRegex = /(https?:\/\/[^\s]+)/g;
    return text.replace(urlRegex, '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>');
}

// Parse emojis (basic implementation)
function parseEmojis(text) {
    // This is a simple implementation. In production, you might want to use a proper emoji library
    return text
        .replace(/:\)/g, '😊')
        .replace(/:\(/g, '😢')
        .replace(/:D/g, '😃')
        .replace(/:P/g, '😛')
        .replace(/<3/g, '❤️')
        .replace(/:\*/g, '😘');
}

// Debounce function
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Throttle function
function throttle(func, limit) {
    let inThrottle;
    return function executedFunction(...args) {
        if (!inThrottle) {
            func.apply(this, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
}

// Generate unique ID
function generateId() {
    return Date.now().toString(36) + Math.random().toString(36).substr(2);
}

// Copy text to clipboard
async function copyToClipboard(text) {
    try {
        if (navigator.clipboard) {
            await navigator.clipboard.writeText(text);
            return true;
        } else {
            // Fallback for older browsers
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.opacity = '0';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            const successful = document.execCommand('copy');
            document.body.removeChild(textArea);
            return successful;
        }
    } catch (error) {
        console.error('Failed to copy text:', error);
        return false;
    }
}

// Validate email
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

// Validate file type and size
function validateFile(file) {
    const errors = [];
    
    if (!file) {
        errors.push('No file selected');
        return errors;
    }
    
    if (file.size > CONFIG.MAX_FILE_SIZE) {
        errors.push(`File size must be less than ${formatFileSize(CONFIG.MAX_FILE_SIZE)}`);
    }
    
    if (!CONFIG.isAllowedFile(file.type)) {
        errors.push('File type not allowed');
    }
    
    return errors;
}

// Get file icon based on mime type
function getFileIcon(mimeType) {
    if (CONFIG.isImage(mimeType)) return 'fas fa-image';
    if (CONFIG.isVideo(mimeType)) return 'fas fa-video';
    if (CONFIG.isAudio(mimeType)) return 'fas fa-music';
    if (mimeType === 'application/pdf') return 'fas fa-file-pdf';
    if (mimeType.includes('word')) return 'fas fa-file-word';
    if (mimeType.includes('excel') || mimeType.includes('spreadsheet')) return 'fas fa-file-excel';
    if (mimeType === 'application/zip') return 'fas fa-file-archive';
    if (mimeType === 'text/plain') return 'fas fa-file-alt';
    return 'fas fa-file';
}

// Scroll to bottom of element
function scrollToBottom(element, smooth = true) {
    if (typeof element === 'string') {
        element = $(element);
    }
    if (element) {
        element.scrollTo({
            top: element.scrollHeight,
            behavior: smooth ? 'smooth' : 'auto'
        });
    }
}

// Check if element is scrolled to bottom
function isScrolledToBottom(element, threshold = 100) {
    if (typeof element === 'string') {
        element = $(element);
    }
    if (!element) return false;
    
    return element.scrollHeight - element.clientHeight <= element.scrollTop + threshold;
}

// Auto-resize textarea
function autoResize(textarea) {
    textarea.style.height = 'auto';
    textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
}

// Show notification
function showNotification(message, type = 'info', duration = CONFIG.NOTIFICATION_TIMEOUT) {
    const container = $('#notification-container');
    if (!container) return;
    
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <p>${escapeHtml(message)}</p>
        </div>
    `;
    
    container.appendChild(notification);
    
    // Auto-remove notification
    setTimeout(() => {
        if (notification.parentNode) {
            notification.style.animation = 'slideOut 0.3s ease-in';
            setTimeout(() => {
                notification.remove();
            }, 300);
        }
    }, duration);
    
    // Add click to close
    notification.addEventListener('click', () => {
        notification.remove();
    });
    
    return notification;
}

// Show loading spinner
function showLoading(element) {
    if (typeof element === 'string') {
        element = $(element);
    }
    if (!element) return;
    
    const spinner = document.createElement('div');
    spinner.className = 'loading-spinner';
    spinner.innerHTML = '<div class="spinner"></div>';
    
    element.appendChild(spinner);
    return spinner;
}

// Hide loading spinner
function hideLoading(spinner) {
    if (spinner && spinner.parentNode) {
        spinner.remove();
    }
}

// Format conversation name
function formatConversationName(conversation, currentUserId) {
    if (conversation.other_user_name) {
        return conversation.other_user_name;
    }
    // Fallback to email if name is not available
    return conversation.other_user_email || 'Unknown User';
}

// Create avatar element
function createAvatar(user, size = 40) {
    const avatar = document.createElement('img');
    avatar.className = 'avatar';
    avatar.style.width = size + 'px';
    avatar.style.height = size + 'px';
    avatar.src = user.avatar_url || user.avatar || 'assets/images/default-avatar.png';
    avatar.alt = user.name || 'User Avatar';
    avatar.onerror = function() {
        this.src = 'assets/images/default-avatar.png';
    };
    return avatar;
}

// Parse message content for links and emojis
function parseMessageContent(content) {
    if (!content) return '';
    
    // Escape HTML first
    let parsed = escapeHtml(content);
    
    // Make URLs clickable
    parsed = linkifyText(parsed);
    
    // Parse emojis
    parsed = parseEmojis(parsed);
    
    return parsed;
}

// Handle keyboard shortcuts
function handleKeyboardShortcuts(event) {
    // Ctrl/Cmd + Enter to send message
    if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
        const messageForm = $('#message-form');
        if (messageForm) {
            event.preventDefault();
            messageForm.dispatchEvent(new Event('submit'));
        }
    }
    
    // Escape to close modals
    if (event.key === 'Escape') {
        const modals = $$('.modal');
        modals.forEach(modal => {
            if (modal.style.display !== 'none') {
                modal.style.display = 'none';
            }
        });
        
        // Hide context menu
        const contextMenu = $('#context-menu');
        if (contextMenu && contextMenu.style.display !== 'none') {
            contextMenu.style.display = 'none';
        }
    }
}

// Initialize keyboard shortcuts
document.addEventListener('keydown', handleKeyboardShortcuts);

// Handle clicks outside elements
function handleClickOutside(event) {
    // Hide context menu when clicking outside
    const contextMenu = $('#context-menu');
    if (contextMenu && contextMenu.style.display !== 'none') {
        if (!contextMenu.contains(event.target)) {
            contextMenu.style.display = 'none';
        }
    }
    
    // Close modals when clicking outside
    const modals = $$('.modal');
    modals.forEach(modal => {
        if (modal.style.display !== 'none') {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        }
    });
}

document.addEventListener('click', handleClickOutside);

// Storage utilities (for settings and cache)
const Storage = {
    set(key, value) {
        try {
            localStorage.setItem(key, JSON.stringify(value));
        } catch (error) {
            console.warn('Failed to save to localStorage:', error);
        }
    },
    
    get(key, defaultValue = null) {
        try {
            const item = localStorage.getItem(key);
            return item ? JSON.parse(item) : defaultValue;
        } catch (error) {
            console.warn('Failed to read from localStorage:', error);
            return defaultValue;
        }
    },
    
    remove(key) {
        try {
            localStorage.removeItem(key);
        } catch (error) {
            console.warn('Failed to remove from localStorage:', error);
        }
    },
    
    clear() {
        try {
            localStorage.clear();
        } catch (error) {
            console.warn('Failed to clear localStorage:', error);
        }
    }
};

// Network status utilities
function isOnline() {
    return navigator.onLine;
}

// Listen for online/offline events
window.addEventListener('online', () => {
    showNotification('Connection restored', 'success');
    eventBus.emit('network:online');
});

window.addEventListener('offline', () => {
    showNotification('Connection lost. Please check your internet connection.', 'warning');
    eventBus.emit('network:offline');
});

// Error handling utility
function handleError(error, context = '') {
    console.error(`Error in ${context}:`, error);
    
    let message = 'An unexpected error occurred';
    
    if (error.message) {
        message = error.message;
    } else if (typeof error === 'string') {
        message = error;
    }
    
    showNotification(message, 'error');
    
    // Log error for debugging (in production, send to error tracking service)
    if (window.DEBUG) {
        console.trace('Error stack trace:', error);
    }
}

// Performance utilities
const Performance = {
    marks: {},
    
    mark(name) {
        this.marks[name] = performance.now();
    },
    
    measure(name, startMark) {
        const endTime = performance.now();
        const startTime = this.marks[startMark];
        if (startTime) {
            const duration = endTime - startTime;
            console.log(`${name}: ${duration.toFixed(2)}ms`);
            return duration;
        }
    }
};

// URL utilities
const URL_UTILS = {
    getParams() {
        return new URLSearchParams(window.location.search);
    },
    
    getParam(name, defaultValue = null) {
        return this.getParams().get(name) || defaultValue;
    },
    
    setParam(name, value) {
        const url = new URL(window.location);
        url.searchParams.set(name, value);
        window.history.replaceState({}, '', url);
    },
    
    removeParam(name) {
        const url = new URL(window.location);
        url.searchParams.delete(name);
        window.history.replaceState({}, '', url);
    }
};

// Device detection
const Device = {
    isMobile() {
        return window.innerWidth <= 768;
    },
    
    isTablet() {
        return window.innerWidth > 768 && window.innerWidth <= 1024;
    },
    
    isDesktop() {
        return window.innerWidth > 1024;
    },
    
    hasTouch() {
        return 'ontouchstart' in window || navigator.maxTouchPoints > 0;
    }
};

// Export utilities to global scope
window.utils = {
    $, $$, show, hide, toggle,
    formatFileSize, formatTime, formatMessageTime,
    escapeHtml, linkifyText, parseEmojis, parseMessageContent,
    debounce, throttle, generateId,
    copyToClipboard, isValidEmail, validateFile,
    getFileIcon, scrollToBottom, isScrolledToBottom, autoResize,
    showNotification, showLoading, hideLoading,
    formatConversationName, createAvatar,
    handleError, Storage, Performance, URL_UTILS, Device
};