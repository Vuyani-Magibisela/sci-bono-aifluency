const CACHE_NAME = 'ai-fluency-cache-v57';
const LESSON_MEDIA_CACHE = 'lesson-media-v1';
const urlsToCache = [
  '/',
  '/index.html',
  '/public/offline.html',
  '/public/login.html',
  '/public/signup.html',
  // Dashboard and Profile pages (Phase 1)
  '/student/dashboard.html',
  '/instructor/dashboard.html',
  '/admin/dashboard.html',
  '/profile/index.html',
  '/public/403.html',
  // Admin Content Management pages (Phase 5B)
  '/admin/courses.html',
  '/admin/modules.html',
  '/admin/lessons.html',
  '/admin/quizzes.html',
  // Dynamic Content Pages (Phase 5C)
  '/student/modules/module-dynamic.html',
  '/student/lessons/lesson-dynamic.html',
  '/student/quizzes/quiz-dynamic.html',
  '/student/quizzes/quiz-history.html',
  // CSS files
  '/css/styles.css',
  '/css/stylesModules.css',
  // Module files (dynamic content loaded via API)
  '/student/modules/module1.html',
  '/student/modules/module2.html',
  '/student/modules/module3.html',
  '/student/modules/module4.html',
  '/student/modules/module5.html',
  '/student/modules/module6.html',
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
  '/student/achievements.html',
  '/student/certificates.html',
  // Walkthrough / Driver.js Tour
  '/js/walkthrough.js',
  '/js/walkthrough-steps.js',
  '/css/walkthrough.css',
  'https://cdn.jsdelivr.net/npm/driver.js@1.3.1/dist/driver.css',
  'https://cdn.jsdelivr.net/npm/driver.js@1.3.1/dist/driver.js.iife.js',
  // Images
  '/images/favicon.ico',
  // External resources
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css',
  'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js',
  'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js',
  // Quill.js (Phase 5B - Rich text editor)
  'https://cdn.quilljs.com/1.3.6/quill.js',
  'https://cdn.quilljs.com/1.3.6/quill.snow.css'
];


// Install event - cache all initial resources
self.addEventListener('install', event => {
  // Skip waiting so the new service worker activates immediately
  self.skipWaiting();
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

  // Runtime cache for lesson media (videos + hero images) — Migration 033.
  // Not pre-cached at install (would be huge); cached on first view so
  // already-viewed lessons replay offline.
  if (url.pathname.includes('/media/lessons/') || url.pathname.includes('/images/lessons/')) {
    event.respondWith(
      caches.open(LESSON_MEDIA_CACHE).then(cache =>
        cache.match(request).then(cached => {
          if (cached) return cached;
          return fetch(request).then(networkResponse => {
            if (networkResponse && networkResponse.ok) {
              cache.put(request, networkResponse.clone());
            }
            return networkResponse;
          }).catch(() => cached);
        })
      )
    );
    return;
  }

  // Network-only strategy for API requests (never cache user-specific data)
  if (url.pathname.startsWith('/api/')) {
    event.respondWith(
      fetch(request)
        .catch(error => {
          // Network failed, try cache as fallback for GET requests (offline support)
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
              return caches.match('/public/offline.html');
            }
          });
      })
  );
});

// Activate event - clean up old caches and take control immediately
self.addEventListener('activate', event => {
  const cacheWhitelist = [CACHE_NAME, LESSON_MEDIA_CACHE];
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          if (cacheWhitelist.indexOf(cacheName) === -1) {
            return caches.delete(cacheName);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});