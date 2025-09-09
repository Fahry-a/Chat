/**
 * API Communication Layer for ChatApp
 */

class API {
    constructor() {
        this.baseURL = CONFIG.API_BASE_URL;
        this.defaultHeaders = {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        };
    }

    // Build full URL
    buildUrl(endpoint) {
        return `${this.baseURL}/${endpoint.replace(/^\//, '')}`;
    }

    // Add CSRF token to headers if available
    getHeaders(customHeaders = {}) {
        const headers = { ...this.defaultHeaders, ...customHeaders };
        
        if (APP_STATE.csrfToken) {
            headers['X-CSRF-Token'] = APP_STATE.csrfToken;
        }
        
        return headers;
    }

    // Generic request method
    async request(method, endpoint, data = null, options = {}) {
        try {
            const url = this.buildUrl(endpoint);
            const config = {
                method: method.toUpperCase(),
                headers: this.getHeaders(options.headers),
                credentials: 'same-origin',
                ...options
            };

            // Handle different data types
            if (data) {
                if (data instanceof FormData) {
                    // Remove Content-Type header for FormData (browser sets it automatically)
                    delete config.headers['Content-Type'];
                    config.body = data;
                } else if (typeof data === 'object') {
                    config.body = JSON.stringify(data);
                } else {
                    config.body = data;
                }
            }

            const response = await fetch(url, config);
            
            // Handle different response types
            const contentType = response.headers.get('Content-Type') || '';
            let responseData;
            
            if (contentType.includes('application/json')) {
                responseData = await response.json();
            } else {
                responseData = await response.text();
            }

            if (!response.ok) {
                const error = new Error(responseData.error || `HTTP ${response.status}: ${response.statusText}`);
                error.status = response.status;
                error.data = responseData;
                throw error;
            }

            // Update CSRF token if provided
            if (responseData.csrf_token) {
                APP_STATE.csrfToken = responseData.csrf_token;
            }

            return responseData;
        } catch (error) {
            // Handle network errors
            if (!navigator.onLine) {
                throw new Error('No internet connection');
            }
            
            // Handle timeout
            if (error.name === 'AbortError') {
                throw new Error('Request timed out');
            }

            throw error;
        }
    }

    // GET request
    async get(endpoint, params = {}) {
        const url = new URL(this.buildUrl(endpoint));
        Object.keys(params).forEach(key => {
            if (params[key] !== null && params[key] !== undefined) {
                url.searchParams.append(key, params[key]);
            }
        });
        
        return this.request('GET', url.pathname + url.search);
    }

    // POST request
    async post(endpoint, data = null) {
        return this.request('POST', endpoint, data);
    }

    // PUT request
    async put(endpoint, data = null) {
        return this.request('PUT', endpoint, data);
    }

    // DELETE request
    async delete(endpoint, data = null) {
        return this.request('DELETE', endpoint, data);
    }

    // File upload with progress
    async uploadFile(endpoint, formData, onProgress = null) {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            
            // Set up progress tracking
            if (onProgress) {
                xhr.upload.addEventListener('progress', (event) => {
                    if (event.lengthComputable) {
                        const percentComplete = (event.loaded / event.total) * 100;
                        onProgress(percentComplete);
                    }
                });
            }

            xhr.addEventListener('load', () => {
                if (xhr.status >= 200 && xhr.status < 300) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.csrf_token) {
                            APP_STATE.csrfToken = response.csrf_token;
                        }
                        resolve(response);
                    } catch (error) {
                        reject(new Error('Invalid JSON response'));
                    }
                } else {
                    try {
                        const errorResponse = JSON.parse(xhr.responseText);
                        reject(new Error(errorResponse.error || `HTTP ${xhr.status}`));
                    } catch (error) {
                        reject(new Error(`HTTP ${xhr.status}: ${xhr.statusText}`));
                    }
                }
            });

            xhr.addEventListener('error', () => {
                reject(new Error('Upload failed'));
            });

            xhr.addEventListener('timeout', () => {
                reject(new Error('Upload timed out'));
            });

            // Set headers
            const headers = this.getHeaders();
            Object.keys(headers).forEach(key => {
                if (key !== 'Content-Type') { // Let browser set Content-Type for FormData
                    xhr.setRequestHeader(key, headers[key]);
                }
            });

            xhr.open('POST', this.buildUrl(endpoint));
            xhr.send(formData);
        });
    }
}

// API endpoints
const ApiEndpoints = {
    // Authentication
    register: 'register.php',
    login: 'login.php',
    logout: 'logout.php',
    
    // Contacts
    contacts: 'contacts.php',
    
    // Conversations
    conversations: 'conversations.php',
    
    // Messages
    messages: 'messages.php',
    sendMessage: 'send_message.php',
    deleteMessage: 'delete_message.php',
    markRead: 'mark_read.php',
    
    // Real-time updates
    polling: 'polling.php'
};

// API service instance
const api = new API();

// Authentication API calls
const AuthAPI = {
    async register(userData) {
        const formData = new FormData();
        
        Object.keys(userData).forEach(key => {
            if (userData[key] !== null && userData[key] !== undefined) {
                if (key === 'avatar' && userData[key] instanceof File) {
                    formData.append(key, userData[key]);
                } else {
                    formData.append(key, userData[key]);
                }
            }
        });
        
        return api.uploadFile(ApiEndpoints.register, formData);
    },

    async login(email, password, remember = false) {
        return api.post(ApiEndpoints.login, {
            email,
            password,
            remember
        });
    },

    async logout() {
        return api.post(ApiEndpoints.logout);
    }
};

// Contacts API calls
const ContactsAPI = {
    async getContacts(search = '') {
        return api.get(ApiEndpoints.contacts, { search });
    },

    async addContact(contactUserId, contactName = null) {
        return api.post(ApiEndpoints.contacts, {
            contact_user_id: contactUserId,
            contact_name: contactName
        });
    }
};

// Conversations API calls
const ConversationsAPI = {
    async getConversations(search = '') {
        return api.get(ApiEndpoints.conversations, { search });
    }
};

// Messages API calls
const MessagesAPI = {
    async getMessages(conversationId, limit = CONFIG.MESSAGE_LOAD_LIMIT, offset = 0) {
        return api.get(ApiEndpoints.messages, {
            conversation_id: conversationId,
            limit,
            offset
        });
    },

    async sendMessage(recipientId, message = '', file = null, onProgress = null) {
        const formData = new FormData();
        formData.append('recipient_id', recipientId);
        
        if (message) {
            formData.append('message', message);
        }
        
        if (file) {
            formData.append('file', file);
        }
        
        if (onProgress) {
            return api.uploadFile(ApiEndpoints.sendMessage, formData, onProgress);
        } else {
            return api.post(ApiEndpoints.sendMessage, formData);
        }
    },

    async deleteMessage(messageId, deleteForAll = false) {
        return api.post(ApiEndpoints.deleteMessage, {
            message_id: messageId,
            delete_for_all: deleteForAll
        });
    },

    async markAsRead(conversationId) {
        return api.post(ApiEndpoints.markRead, {
            conversation_id: conversationId
        });
    }
};

// Polling API calls
const PollingAPI = {
    async getUpdates(since, conversationId = null) {
        const params = { since };
        if (conversationId) {
            params.conversation_id = conversationId;
        }
        return api.get(ApiEndpoints.polling, params);
    }
};

// Error handling wrapper
function withErrorHandling(apiCall, context = '') {
    return async (...args) => {
        try {
            return await apiCall(...args);
        } catch (error) {
            console.error(`API Error in ${context}:`, error);
            
            // Handle specific error cases
            if (error.status === 401) {
                // Unauthorized - redirect to login
                eventBus.emit('auth:unauthorized');
                return null;
            }
            
            if (error.status === 403) {
                // Forbidden
                showNotification('Access denied', 'error');
                return null;
            }
            
            if (error.status === 429) {
                // Rate limited
                showNotification('Too many requests. Please try again later.', 'warning');
                return null;
            }
            
            if (error.status >= 500) {
                // Server error
                showNotification('Server error. Please try again later.', 'error');
                return null;
            }
            
            // Re-throw error for specific handling
            throw error;
        }
    };
}

// Wrap all API calls with error handling
const safeAuthAPI = {
    register: withErrorHandling(AuthAPI.register, 'register'),
    login: withErrorHandling(AuthAPI.login, 'login'),
    logout: withErrorHandling(AuthAPI.logout, 'logout')
};

const safeContactsAPI = {
    getContacts: withErrorHandling(ContactsAPI.getContacts, 'getContacts'),
    addContact: withErrorHandling(ContactsAPI.addContact, 'addContact')
};

const safeConversationsAPI = {
    getConversations: withErrorHandling(ConversationsAPI.getConversations, 'getConversations')
};

const safeMessagesAPI = {
    getMessages: withErrorHandling(MessagesAPI.getMessages, 'getMessages'),
    sendMessage: withErrorHandling(MessagesAPI.sendMessage, 'sendMessage'),
    deleteMessage: withErrorHandling(MessagesAPI.deleteMessage, 'deleteMessage'),
    markAsRead: withErrorHandling(MessagesAPI.markAsRead, 'markAsRead')
};

const safePollingAPI = {
    getUpdates: withErrorHandling(PollingAPI.getUpdates, 'getUpdates')
};

// Export API objects
window.api = {
    auth: safeAuthAPI,
    contacts: safeContactsAPI,
    conversations: safeConversationsAPI,
    messages: safeMessagesAPI,
    polling: safePollingAPI,
    raw: {
        auth: AuthAPI,
        contacts: ContactsAPI,
        conversations: ConversationsAPI,
        messages: MessagesAPI,
        polling: PollingAPI
    }
};