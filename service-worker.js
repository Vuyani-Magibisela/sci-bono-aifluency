const CACHE_NAME = 'ai-fluency-cache-v1';
const urlsToCache = [
  '/',
  '/index.html',
  '/offline.html', // Add this here, not at the bottom
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
  // JavaScript files
  '/js/script.js',
  // Images (add all your image paths here)
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

// Fetch event - serve from cache first, then network
self.addEventListener('fetch', event => {
  event.respondWith(
    caches.match(event.request)
      .then(response => {
        // Cache hit - return response
        if (response) {
          return response;
        }
        
        // Clone the request
        const fetchRequest = event.request.clone();
        
        return fetch(fetchRequest)
          .then(response => {
            // Check if valid response
            if (!response || response.status !== 200 || response.type !== 'basic') {
              return response;
            }
            
            // Clone the response
            const responseToCache = response.clone();
            
            // Add to cache
            caches.open(CACHE_NAME)
              .then(cache => {
                cache.put(event.request, responseToCache);
              });
              
            return response;
          })
          .catch(error => {
            // Network request failed, show offline page for HTML requests
            if (event.request.headers.get('accept').includes('text/html')) {
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