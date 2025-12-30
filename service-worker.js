const CACHE_NAME = 'ai-fluency-cache-v31';
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
  // Admin Content Management pages (Phase 5B)
  '/admin-courses.html',
  '/admin-modules.html',
  '/admin-lessons.html',
  '/admin-quizzes.html',
  // Dynamic Content Pages (Phase 5C)
  '/module-dynamic.html',
  '/lesson-dynamic.html',
  '/quiz-dynamic.html',
  '/quiz-history.html',
  // CSS files
  '/css/styles.css',
  '/css/stylesModules.css',
  // Module files (dynamic content loaded via API)
  '/module1.html',
  '/module2.html',
  '/module3.html',
  '/module4.html',
  '/module5.html',
  '/module6.html',
  // JavaScript files (Phase 1)
  '/js/script.js',
  '/js/storage.js',
  '/js/api.js',
  '/js/auth.js',
  '/js/header-template.js',
  '/js/footer-template.js',
  // Dashboard JavaScript files (Phase 4)
  '/js/dashboard.js',
  '/js/instructor.js',
  '/js/admin.js',
  // Admin Content Management JavaScript files (Phase 5B)
  '/js/admin-courses.js',
  '/js/admin-modules.js',
  '/js/admin-lessons.js',
  '/js/admin-quizzes.js',
  // Content Loader (Phase 5C)
  '/js/content-loader.js',
  // Breadcrumb Navigation (Phase 5D Priority 2)
  '/js/breadcrumb.js',
  // Quiz History (Phase 5D Priority 3)
  '/js/quiz-history.js',
  // Achievements (Phase 6)
  '/js/achievements.js',
  '/achievements.html',
  '/certificates.html',
  // Images
  '/images/favicon.ico',
  // External resources
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css',
  'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js',
  'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js',
  // Quill.js (Phase 5B - Rich text editor)
  'https://cdn.quilljs.com/1.3.6/quill.js',
  'https://cdn.quilljs.com/1.3.6/quill.snow.css'
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