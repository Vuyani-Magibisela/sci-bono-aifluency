const CACHE_NAME = 'ai-fluency-cache-v3';
const urlsToCache = [
  '/',
  '/index.html',
  '/offline.html',
  '/login.html',
  '/signup.html',
  // Dashboard and Profile pages (Phase 1)
  '/student-dashboard.html',
  '/instructor-dashboard.html',
  '/admin-dashboard.html',
  '/profile.html',
  '/403.html',
  // CSS files
  '/css/styles.css',
  '/css/stylesModules.css',
  // Chapter files
  '/chapter1.html',
  '/chapter1_17.html',
  '/chapter1_24.html',
  '/chapter1_28.html',
  '/chapter1_40.html',
  '/chapter2.html',
  '/chapter2_12.html',
  '/chapter2_18.html',
  '/chapter2_25.html',
  '/chapter2_41.html',
  '/chapter3.html',
  '/chapter3_13.html',
  '/chapter3_19.html',
  '/chapter3_26.html',
  '/chapter3_30.html',
  '/chapter3_42.html',
  '/chapter4.html',
  '/chapter4_14.html',
  '/chapter4_20.html',
  '/chapter4_27.html',
  '/chapter4_31.html',
  '/chapter4_43.html',
  '/chapter5.html',
  '/chapter5_15.html',
  '/chapter5_21.html',
  '/chapter5_32.html',
  '/chapter6.html',
  '/chapter6_16.html',
  '/chapter6_22.html',
  '/chapter6_33.html',
  '/chapter7.html',
  '/chapter7_23.html',
  '/chapter7_34.html',
  '/chapter8.html',
  '/chapter8_35.html',
  '/chapter9.html',
  '/chapter9_36.html',
  '/chapter10.html',
  '/chapter10_37.html',
  '/chapter11.html',
  '/chapter12_39.html',
  // Module files
  '/module1.html',
  '/module2.html',
  '/module3.html',
  '/module4.html',
  '/module5.html',
  '/module6.html',
  '/module1Quiz.html',
  '/module2Quiz.html',
  '/module3Quiz.html',
  '/module4Quiz.html',
  '/module5Quiz.html',
  '/module6Quiz.html',
  // JavaScript files (Phase 1)
  '/js/script.js',
  '/js/storage.js',
  '/js/api.js',
  '/js/auth.js',
  '/js/header-template.js',
  '/js/footer-template.js',
  // Images
  '/images/favicon.ico',
  // External resources
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css',
  'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js',
  'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js'
];


// Install event - cache all initial resources
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('Opened cache');
        return cache.addAll(urlsToCache);
      })
  );
});

// Fetch event - different strategies for API vs static content
self.addEventListener('fetch', event => {
  const { request } = event;
  const url = new URL(request.url);

  // Network-first strategy for API requests (always get fresh data)
  if (url.pathname.startsWith('/api/')) {
    event.respondWith(
      fetch(request)
        .then(response => {
          // Clone and cache successful API responses (except auth endpoints)
          if (response && response.status === 200 && !url.pathname.includes('/auth/')) {
            const responseToCache = response.clone();
            caches.open(CACHE_NAME).then(cache => {
              cache.put(request, responseToCache);
            });
          }
          return response;
        })
        .catch(error => {
          // Network failed, try cache as fallback for GET requests
          if (request.method === 'GET') {
            return caches.match(request).then(cached => {
              if (cached) {
                return cached;
              }
              // No cache available, return error response
              return new Response(
                JSON.stringify({
                  success: false,
                  message: 'Network error. Please check your connection.'
                }),
                {
                  status: 503,
                  headers: { 'Content-Type': 'application/json' }
                }
              );
            });
          }
          throw error;
        })
    );
    return;
  }

  // Cache-first strategy for static content (offline-first PWA)
  event.respondWith(
    caches.match(request)
      .then(response => {
        // Cache hit - return cached response
        if (response) {
          return response;
        }

        // Not in cache - fetch from network
        const fetchRequest = request.clone();

        return fetch(fetchRequest)
          .then(response => {
            // Check if valid response
            if (!response || response.status !== 200 || response.type !== 'basic') {
              return response;
            }

            // Clone and cache the response
            const responseToCache = response.clone();

            caches.open(CACHE_NAME)
              .then(cache => {
                cache.put(request, responseToCache);
              });

            return response;
          })
          .catch(error => {
            // Network request failed, show offline page for HTML requests
            if (request.headers.get('accept') && request.headers.get('accept').includes('text/html')) {
              return caches.match('/offline.html');
            }
          });
      })
  );
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
  const cacheWhitelist = [CACHE_NAME];
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          if (cacheWhitelist.indexOf(cacheName) === -1) {
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
});