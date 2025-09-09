/**
 * ChatApp Configuration
 */
const CONFIG = {
    API_BASE_URL: '/chatapp/api',
    UPLOAD_BASE_URL: '/chatapp/uploads',
    POLLING_INTERVAL: 3000, // 3 seconds
    MAX_FILE_SIZE: 20 * 1024 * 1024, // 20MB
    ALLOWED_IMAGE_TYPES: ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
    ALLOWED_VIDEO_TYPES: ['video/mp4', 'video/webm', 'video/ogg'],
    ALLOWED_AUDIO_TYPES: ['audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/mp4'],
    ALLOWED_FILE_TYPES: [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/zip',
        'text/plain',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    ],
    MESSAGE_LOAD_LIMIT: 50,
    CONTACT_SEARCH_DELAY: 300, // milliseconds
    TYPING_TIMEOUT: 3000, // 3 seconds
    NOTIFICATION_TIMEOUT: 5000, // 5 seconds
    AUTO_SCROLL_THRESHOLD: 100, // pixels from bottom
    EMOJI_REGEX: /[\u{1F600}-\u{1F64F}]|[\u{1F300}-\u{1F5FF}]|[\u{1F680}-\u{1F6FF}]|[\u{1F1E0}-\u{1F1FF}]|[\u{2600}-\u{26FF}]|[\u{2700}-\u{27BF}]/gu
};

// File type helpers
CONFIG.isImage = function(mimeType) {
    return this.ALLOWED_IMAGE_TYPES.includes(mimeType);
};

CONFIG.isVideo = function(mimeType) {
    return this.ALLOWED_VIDEO_TYPES.includes(mimeType);
};

CONFIG.isAudio = function(mimeType) {
    return this.ALLOWED_AUDIO_TYPES.includes(mimeType);
};

CONFIG.isAllowedFile = function(mimeType) {
    return [...this.ALLOWED_IMAGE_TYPES, ...this.ALLOWED_VIDEO_TYPES, ...this.ALLOWED_AUDIO_TYPES, ...this.ALLOWED_FILE_TYPES].includes(mimeType);
};

// URL builders
CONFIG.buildApiUrl = function(endpoint) {
    return this.API_BASE_URL + '/' + endpoint.replace(/^\//, '');
};

CONFIG.buildUploadUrl = function(path) {
    return this.UPLOAD_BASE_URL + '/' + path.replace(/^\//, '');
};

// Application state
const APP_STATE = {
    currentUser: null,
    currentConversation: null,
    conversations: [],
    contacts: [],
    selectedFiles: [],
    isTyping: false,
    lastPollTime: null,
    pollingActive: false,
    csrfToken: null,
    contextMenuTarget: null
};

// Event emitter for app-wide events
class EventEmitter {
    constructor() {
        this.events = {};
    }

    on(event, callback) {
        if (!this.events[event]) {
            this.events[event] = [];
        }
        this.events[event].push(callback);
    }

    off(event, callback) {
        if (!this.events[event]) return;
        this.events[event] = this.events[event].filter(cb => cb !== callback);
    }

    emit(event, data) {
        if (!this.events[event]) return;
        this.events[event].forEach(callback => {
            try {
                callback(data);
            } catch (error) {
                console.error('Error in event callback:', error);
            }
        });
    }
}

const eventBus = new EventEmitter();

// Export for use in other files
window.CONFIG = CONFIG;
window.APP_STATE = APP_STATE;
window.eventBus = eventBus;