const CACHE_NAME = 'battlepool-shell-v1';
const DYNAMIC_CACHE_NAME = 'battlepool-dynamic-v1';

// Static assets to pre-cache immediately on installation
const STATIC_ASSETS = [
  '/',
  '/css/style.css',
  '/icon.svg',
  '/pwa_icon_192.png',
  '/pwa_icon_512.png',
  '/favicon.ico',
  'https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap',
  'https://unpkg.com/lucide@0.344.0',
  'https://cdnjs.cloudflare.com/ajax/libs/web3/1.8.0/web3.min.js'
];

// Exclude these paths from any caching
const EXCLUDED_PATHS = [
  '/user/',
  '/budget/',
  '/wallet/',
  '/artefacts',
  '/notifications/',
  '/csrf-token',
  '/api/',
  '/broadcasting/'
];

// Install Service Worker
self.addEventListener('install', (event) => {
  console.log('[Service Worker] Installing...');
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      console.log('[Service Worker] Pre-caching app shell...');
      return cache.addAll(STATIC_ASSETS);
    }).then(() => self.skipWaiting())
  );
});

// Activate Service Worker & Clean Up Old Caches
self.addEventListener('activate', (event) => {
  console.log('[Service Worker] Activating...');
  event.waitUntil(
    caches.keys().then((cacheKeys) => {
      return Promise.all(
        cacheKeys.map((key) => {
          if (key !== CACHE_NAME && key !== DYNAMIC_CACHE_NAME) {
            console.log('[Service Worker] Removing old cache:', key);
            return caches.delete(key);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

// Fetch Interception
self.addEventListener('fetch', (event) => {
  const request = event.request;
  const url = new URL(request.url);

  // 1. Only handle GET requests
  if (request.method !== 'GET') {
    return;
  }

  // 2. Ignore Chrome Extensions, WebSockets, or non-http protocols
  if (!request.url.startsWith(self.location.origin) && !request.url.startsWith('http')) {
    return;
  }

  // 3. Exclude backend API, Web3 auth, and CSRF endpoints
  const isExcluded = EXCLUDED_PATHS.some(path => url.pathname.includes(path));
  if (isExcluded) {
    return;
  }

  // 4. Strategy: Network-First for Page Navigation (HTML)
  if (request.mode === 'navigate' || url.pathname === '/' || url.pathname === '/dashboard' || url.pathname === '/autoplay') {
    event.respondWith(
      fetch(request)
        .then((response) => {
          // Put page in shell cache
          const responseClone = response.clone();
          caches.open(CACHE_NAME).then((cache) => {
            cache.put(request, responseClone);
          });
          return response;
        })
        .catch(() => {
          // If network fails, serve from cache
          return caches.match(request).then((cachedResponse) => {
            if (cachedResponse) return cachedResponse;
            // Fallback to cached root
            return caches.match('/');
          });
        })
    );
    return;
  }

  // 5. Strategy: Cache-First for static assets (CSS, JS, Fonts, Images, CDNs)
  const isStaticAsset = 
    url.pathname.includes('/build/assets/') || 
    url.pathname.endsWith('.css') || 
    url.pathname.endsWith('.js') || 
    url.pathname.endsWith('.png') || 
    url.pathname.endsWith('.svg') || 
    url.pathname.endsWith('.woff2') ||
    request.url.includes('fonts.googleapis.com') ||
    request.url.includes('fonts.gstatic.com') ||
    request.url.includes('cdnjs.cloudflare.com') ||
    request.url.includes('unpkg.com');

  if (isStaticAsset) {
    event.respondWith(
      caches.match(request).then((cachedResponse) => {
        if (cachedResponse) {
          return cachedResponse;
        }

        return fetch(request).then((response) => {
          // Cache successful responses
          if (response.status === 200) {
            const responseClone = response.clone();
            caches.open(DYNAMIC_CACHE_NAME).then((cache) => {
              cache.put(request, responseClone);
            });
          }
          return response;
        }).catch(() => {
          // Fallback if offline
          return new Response('Offline asset unavailable', { status: 503, statusText: 'Service Unavailable' });
        });
      })
    );
    return;
  }

  // 6. Default: Network with Cache Fallback for everything else
  event.respondWith(
    fetch(request)
      .then((response) => {
        return response;
      })
      .catch(() => {
        return caches.match(request);
      })
  );
});
