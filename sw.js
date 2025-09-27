/**
 * Service Worker for Student Profile Management System
 * Handles offline functionality, caching, and background sync
 */

const CACHE_NAME = 'student-profiles-v1.0.0';
const STATIC_CACHE = 'static-v1.0.0';
const DYNAMIC_CACHE = 'dynamic-v1.0.0';

// Files to cache for offline functionality
const STATIC_FILES = [
    '/',
    '/index.php',
    '/login.php',
    '/register.php',
    '/profile.php',
    '/assets/css/style.css',
    '/manifest.json',
    '/assets/icons/icon-192x192.png',
    '/assets/icons/icon-512x512.png'
];

// API endpoints that should be cached
const API_CACHE_PATTERNS = [
    /\/api\/profile/,
    /\/api\/dashboard/,
    /\/api\/users/
];

// Install event - cache static files
self.addEventListener('install', event => {
    console.log('Service Worker: Installing...');
    
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then(cache => {
                console.log('Service Worker: Caching static files');
                return cache.addAll(STATIC_FILES);
            })
            .then(() => {
                console.log('Service Worker: Static files cached');
                return self.skipWaiting();
            })
            .catch(error => {
                console.error('Service Worker: Failed to cache static files', error);
            })
    );
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
    console.log('Service Worker: Activating...');
    
    event.waitUntil(
        caches.keys()
            .then(cacheNames => {
                return Promise.all(
                    cacheNames.map(cacheName => {
                        if (cacheName !== STATIC_CACHE && cacheName !== DYNAMIC_CACHE) {
                            console.log('Service Worker: Deleting old cache', cacheName);
                            return caches.delete(cacheName);
                        }
                    })
                );
            })
            .then(() => {
                console.log('Service Worker: Activated');
                return self.clients.claim();
            })
    );
});

// Fetch event - handle requests with caching strategies
self.addEventListener('fetch', event => {
    const { request } = event;
    const url = new URL(request.url);
    
    // Skip non-GET requests
    if (request.method !== 'GET') {
        return;
    }
    
    // Handle different types of requests
    if (request.destination === 'document') {
        // HTML pages - network first, then cache
        event.respondWith(handleDocumentRequest(request));
    } else if (request.destination === 'style' || request.destination === 'script') {
        // CSS/JS files - cache first, then network
        event.respondWith(handleStaticRequest(request));
    } else if (request.destination === 'image') {
        // Images - cache first, then network
        event.respondWith(handleImageRequest(request));
    } else if (isApiRequest(url)) {
        // API requests - network first, then cache
        event.respondWith(handleApiRequest(request));
    } else {
        // Default - network first
        event.respondWith(fetch(request));
    }
});

// Handle document requests (HTML pages)
async function handleDocumentRequest(request) {
    try {
        // Try network first
        const networkResponse = await fetch(request);
        
        // If successful, cache the response
        if (networkResponse.ok) {
            const cache = await caches.open(DYNAMIC_CACHE);
            cache.put(request, networkResponse.clone());
        }
        
        return networkResponse;
    } catch (error) {
        console.log('Service Worker: Network failed, trying cache for', request.url);
        
        // Try cache
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        
        // If it's a navigation request and we're offline, show offline page
        if (request.mode === 'navigate') {
            return caches.match('/offline.html') || new Response(
                getOfflinePage(),
                {
                    headers: { 'Content-Type': 'text/html' }
                }
            );
        }
        
        throw error;
    }
}

// Handle static requests (CSS, JS)
async function handleStaticRequest(request) {
    try {
        // Try cache first
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        
        // Try network
        const networkResponse = await fetch(request);
        
        // Cache the response
        if (networkResponse.ok) {
            const cache = await caches.open(STATIC_CACHE);
            cache.put(request, networkResponse.clone());
        }
        
        return networkResponse;
    } catch (error) {
        console.error('Service Worker: Failed to fetch static resource', request.url);
        throw error;
    }
}

// Handle image requests
async function handleImageRequest(request) {
    try {
        // Try cache first
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        
        // Try network
        const networkResponse = await fetch(request);
        
        // Cache the response
        if (networkResponse.ok) {
            const cache = await caches.open(DYNAMIC_CACHE);
            cache.put(request, networkResponse.clone());
        }
        
        return networkResponse;
    } catch (error) {
        // Return a placeholder image if available
        const placeholderResponse = await caches.match('/assets/icons/icon-192x192.png');
        return placeholderResponse || new Response('', { status: 404 });
    }
}

// Handle API requests
async function handleApiRequest(request) {
    try {
        // Try network first
        const networkResponse = await fetch(request);
        
        // If successful, cache the response
        if (networkResponse.ok) {
            const cache = await caches.open(DYNAMIC_CACHE);
            cache.put(request, networkResponse.clone());
        }
        
        return networkResponse;
    } catch (error) {
        console.log('Service Worker: Network failed, trying cache for API', request.url);
        
        // Try cache
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        
        // Return offline response for API
        return new Response(
            JSON.stringify({
                error: 'Offline',
                message: 'This feature is not available offline'
            }),
            {
                status: 503,
                headers: { 'Content-Type': 'application/json' }
            }
        );
    }
}

// Check if request is an API request
function isApiRequest(url) {
    return API_CACHE_PATTERNS.some(pattern => pattern.test(url.pathname));
}

// Background sync for offline actions
self.addEventListener('sync', event => {
    console.log('Service Worker: Background sync triggered', event.tag);
    
    if (event.tag === 'profile-update') {
        event.waitUntil(syncProfileUpdates());
    } else if (event.tag === 'form-submission') {
        event.waitUntil(syncFormSubmissions());
    }
});

// Sync profile updates when back online
async function syncProfileUpdates() {
    try {
        // Get pending updates from IndexedDB
        const pendingUpdates = await getPendingUpdates();
        
        for (const update of pendingUpdates) {
            try {
                const response = await fetch(update.url, {
                    method: update.method,
                    headers: update.headers,
                    body: update.body
                });
                
                if (response.ok) {
                    // Remove from pending updates
                    await removePendingUpdate(update.id);
                    console.log('Service Worker: Synced profile update', update.id);
                }
            } catch (error) {
                console.error('Service Worker: Failed to sync update', update.id, error);
            }
        }
    } catch (error) {
        console.error('Service Worker: Background sync failed', error);
    }
}

// Sync form submissions when back online
async function syncFormSubmissions() {
    try {
        const pendingSubmissions = await getPendingSubmissions();
        
        for (const submission of pendingSubmissions) {
            try {
                const response = await fetch(submission.url, {
                    method: submission.method,
                    headers: submission.headers,
                    body: submission.body
                });
                
                if (response.ok) {
                    await removePendingSubmission(submission.id);
                    console.log('Service Worker: Synced form submission', submission.id);
                }
            } catch (error) {
                console.error('Service Worker: Failed to sync submission', submission.id, error);
            }
        }
    } catch (error) {
        console.error('Service Worker: Form sync failed', error);
    }
}

// Push notifications
self.addEventListener('push', event => {
    console.log('Service Worker: Push notification received');
    
    const options = {
        body: event.data ? event.data.text() : 'New notification from Student Profiles',
        icon: '/assets/icons/icon-192x192.png',
        badge: '/assets/icons/icon-72x72.png',
        vibrate: [200, 100, 200],
        data: {
            dateOfArrival: Date.now(),
            primaryKey: 1
        },
        actions: [
            {
                action: 'explore',
                title: 'View Details',
                icon: '/assets/icons/action-view.png'
            },
            {
                action: 'close',
                title: 'Close',
                icon: '/assets/icons/action-close.png'
            }
        ]
    };
    
    event.waitUntil(
        self.registration.showNotification('Student Profiles', options)
    );
});

// Handle notification clicks
self.addEventListener('notificationclick', event => {
    console.log('Service Worker: Notification clicked', event.notification.tag);
    
    event.notification.close();
    
    if (event.action === 'explore') {
        event.waitUntil(
            clients.openWindow('/')
        );
    } else if (event.action === 'close') {
        // Just close the notification
    } else {
        // Default action - open the app
        event.waitUntil(
            clients.openWindow('/')
        );
    }
});

// Message handling for communication with main thread
self.addEventListener('message', event => {
    console.log('Service Worker: Message received', event.data);
    
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    } else if (event.data && event.data.type === 'CACHE_URLS') {
        event.waitUntil(
            caches.open(DYNAMIC_CACHE)
                .then(cache => cache.addAll(event.data.urls))
        );
    }
});

// Helper functions for IndexedDB operations
async function getPendingUpdates() {
    // Implementation would use IndexedDB to get pending updates
    return [];
}

async function removePendingUpdate(id) {
    // Implementation would use IndexedDB to remove pending update
}

async function getPendingSubmissions() {
    // Implementation would use IndexedDB to get pending submissions
    return [];
}

async function removePendingSubmission(id) {
    // Implementation would use IndexedDB to remove pending submission
}

// Offline page HTML
function getOfflinePage() {
    return `
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Offline - Student Profiles</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #0a0a0a;
            color: #ffffff;
            margin: 0;
            padding: 2rem;
            text-align: center;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .offline-container {
            max-width: 400px;
        }
        .offline-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        h1 {
            color: #00ff88;
            margin-bottom: 1rem;
        }
        p {
            color: #cccccc;
            margin-bottom: 2rem;
        }
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background-color: #00ff88;
            color: #000000;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn:hover {
            background-color: #00cc6a;
            transform: translateY(-1px);
        }
    </style>
</head>
<body>
    <div class="offline-container">
        <div class="offline-icon">📱</div>
        <h1>You're Offline</h1>
        <p>It looks like you're not connected to the internet. Some features may not be available, but you can still view cached content.</p>
        <a href="/" class="btn">Go to Dashboard</a>
    </div>
</body>
</html>
    `;
}
