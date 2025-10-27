# Service Worker Implementation Guide - Sci-Bono AI Fluency Platform

**Document Version:** 1.0
**Last Updated:** 2025-10-27
**Author:** Development Team
**Status:** Complete - Current PWA Implementation

---

## Table of Contents

1. [Introduction](#introduction)
2. [Service Worker Overview](#service-worker-overview)
3. [File Structure](#file-structure)
4. [Service Worker Lifecycle](#service-worker-lifecycle)
5. [Installation Phase](#installation-phase)
6. [Activation Phase](#activation-phase)
7. [Fetch Handling](#fetch-handling)
8. [Caching Strategy](#caching-strategy)
9. [Cache Management](#cache-management)
10. [Offline Support](#offline-support)
11. [Service Worker Registration](#service-worker-registration)
12. [Web App Manifest](#web-app-manifest)
13. [Testing & Debugging](#testing--debugging)
14. [Update Procedures](#update-procedures)
15. [Troubleshooting](#troubleshooting)
16. [Browser Compatibility](#browser-compatibility)
17. [Performance Optimization](#performance-optimization)
18. [Security Considerations](#security-considerations)
19. [Future Enhancements](#future-enhancements)
20. [Related Documents](#related-documents)

---

## Introduction

### Purpose

This guide provides comprehensive documentation for the Service Worker implementation that enables Progressive Web App (PWA) functionality in the Sci-Bono AI Fluency platform. It covers caching strategies, offline support, and maintenance procedures.

### What is a Service Worker?

A **Service Worker** is a JavaScript file that runs in the background, separate from the main browser thread. It acts as a programmable network proxy, allowing you to control how network requests are handled.

**Key Capabilities:**
- **Offline functionality** - Serve cached content when offline
- **Background sync** - Sync data when connection is restored
- **Push notifications** - Receive server-sent notifications
- **Caching** - Store assets for faster load times
- **Interception** - Intercept and modify network requests

**Important:** Service Workers require HTTPS (except on localhost for development).

---

## Service Worker Overview

### Current Implementation

**File:** `/service-worker.js` (140 lines)

**Capabilities Implemented:**
- ✅ **Cache-first strategy** - Serve from cache, fallback to network
- ✅ **Offline page** - Show custom offline page when network fails
- ✅ **Runtime caching** - Cache new resources as they're fetched
- ✅ **Version management** - Update cache when code changes
- ✅ **Old cache cleanup** - Remove outdated caches

**Not Yet Implemented:**
- ❌ Background sync
- ❌ Push notifications
- ❌ Periodic background sync
- ❌ Advanced caching strategies (network-first, stale-while-revalidate)

---

## File Structure

### Service Worker Files

```
/
├── service-worker.js          # Main service worker file
├── manifest.json              # Web app manifest
├── offline.html               # Offline fallback page
└── images/
    ├── android-chrome-192x192.png
    ├── android-chrome-512x512.png
    ├── apple-touch-icon.png
    ├── screenshot-desktop.png
    └── screenshot-mobile.png
```

### Service Worker Scope

**Registration Path:** `/service-worker.js`
**Scope:** `/` (entire site)

**What This Means:**
- Service Worker can intercept all requests under the root domain
- All pages can benefit from caching and offline support
- One Service Worker handles the entire application

---

## Service Worker Lifecycle

### Lifecycle Phases

```
┌─────────────────────────────────────────────────────────────┐
│                    SERVICE WORKER LIFECYCLE                  │
└─────────────────────────────────────────────────────────────┘

        User visits site
              ↓
    ┌─────────────────────┐
    │  1. REGISTRATION    │  navigator.serviceWorker.register()
    └──────────┬──────────┘
               ↓
    ┌─────────────────────┐
    │  2. INSTALLATION    │  install event fires
    │                     │  Cache initial resources
    └──────────┬──────────┘
               ↓
    ┌─────────────────────┐
    │  3. WAITING         │  New SW waits for old SW to release
    │     (if update)     │
    └──────────┬──────────┘
               ↓
    ┌─────────────────────┐
    │  4. ACTIVATION      │  activate event fires
    │                     │  Clean up old caches
    └──────────┬──────────┘
               ↓
    ┌─────────────────────┐
    │  5. ACTIVE          │  SW controls pages
    │                     │  Intercepts fetch events
    └──────────┬──────────┘
               ↓
    ┌─────────────────────┐
    │  6. FETCH           │  fetch event fires on network requests
    │                     │  Serve from cache or network
    └─────────────────────┘
               ↓
    ┌─────────────────────┐
    │  7. IDLE            │  SW waits for events
    └─────────────────────┘
               ↓
    (Repeat fetch events or terminate)
```

### Event Flow

**First Visit:**
1. Browser downloads `service-worker.js`
2. **Install event** fires → Caches initial resources
3. **Activate event** fires → Service Worker takes control
4. **Fetch events** handled for subsequent requests

**Return Visit (No Updates):**
1. Service Worker already installed
2. **Fetch events** handled immediately
3. Content served from cache

**Return Visit (With Updates):**
1. Browser detects changed `service-worker.js`
2. New Service Worker installs in background
3. Waits for old Service Worker to close
4. **Activate event** fires → Cleans up old caches
5. New Service Worker takes control

---

## Installation Phase

### Install Event Handler

**Location:** `service-worker.js:75-83`

**Code:**
```javascript
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('Opened cache');
        return cache.addAll(urlsToCache);
      })
  );
});
```

### How It Works

**Step-by-Step Process:**

1. **Service Worker downloads** - Browser fetches `service-worker.js`
2. **Install event fires** - Triggers installation process
3. **`event.waitUntil()`** - Extends event lifetime until promise resolves
4. **`caches.open(CACHE_NAME)`** - Opens or creates cache storage
5. **`cache.addAll(urlsToCache)`** - Fetches and caches all URLs
6. **Promise resolves** - Installation complete, SW moves to "installed" state

### Key Methods

#### event.waitUntil()

**Purpose:** Tells the browser that work is ongoing until the promise settles.

**Why It's Important:**
- Prevents Service Worker from installing until caching is complete
- Ensures all resources are cached before SW activates
- Installation fails if any resource fails to cache

**Example:**
```javascript
event.waitUntil(
  // This promise must resolve for installation to succeed
  caches.open('my-cache').then(cache => cache.addAll(['/index.html']))
);
```

#### caches.open(cacheName)

**Returns:** `Promise<Cache>`

**Purpose:** Opens a named cache storage.

**Behavior:**
- Creates cache if it doesn't exist
- Returns existing cache if already present
- Cache name is case-sensitive

**Example:**
```javascript
caches.open('ai-fluency-cache-v1')
  .then(cache => {
    // Work with cache
  });
```

#### cache.addAll(urls)

**Parameters:** `Array<string>` - Array of URLs to cache

**Returns:** `Promise<void>`

**Behavior:**
- Fetches all URLs in parallel
- Caches all responses atomically (all-or-nothing)
- **Fails entirely** if any single URL fails to fetch
- Follows redirects
- Only caches HTTP 200 (OK) responses

**Important:** If ONE URL fails, the ENTIRE installation fails.

**Example:**
```javascript
cache.addAll([
  '/',
  '/index.html',
  '/styles.css',
  '/script.js'
])
.then(() => console.log('All cached!'))
.catch(err => console.error('Caching failed:', err));
```

---

## Cache Configuration

### Cache Name

**Location:** `service-worker.js:1`

```javascript
const CACHE_NAME = 'ai-fluency-cache-v1';
```

**Naming Convention:** `{app-name}-cache-v{version}`

**Version Management:**
- Increment version when files change (v1 → v2 → v3)
- Old caches are deleted during activation
- Allows controlled cache updates

**Example Version Progression:**
```javascript
// Initial release
const CACHE_NAME = 'ai-fluency-cache-v1';

// After content update
const CACHE_NAME = 'ai-fluency-cache-v2';

// After major redesign
const CACHE_NAME = 'ai-fluency-cache-v3';
```

---

### URLs to Cache

**Location:** `service-worker.js:2-71`

**Current Implementation:**
```javascript
const urlsToCache = [
  // Core pages
  '/',
  '/index.html',
  '/offline.html',

  // Stylesheets
  '/css/styles.css',
  '/css/stylesModules.css',

  // Chapter files (50+ files)
  '/chapter1.html',
  '/chapter1_17.html',
  // ... (all chapter files)

  // Module files
  '/module1.html',
  '/module2.html',
  // ... (all module files)

  // Quiz files
  '/module1Quiz.html',
  '/module2Quiz.html',
  // ... (all quiz files)

  // JavaScript
  '/js/script.js',

  // Images
  '/images/favicon.ico',

  // External resources (CDN)
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css',
  'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js',
  'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js'
];
```

**Total Resources:** 70+ files

---

### Resource Categories

**1. Core Application Files:**
```javascript
'/',               // Root
'/index.html',     // Landing page
'/offline.html'    // Offline fallback
```

**2. Stylesheets:**
```javascript
'/css/styles.css',        // Main styles
'/css/stylesModules.css'  // Module-specific styles
```

**3. Content Pages:**
- **Chapters:** 50+ individual lesson pages
- **Modules:** 6 module overview pages
- **Quizzes:** 6 quiz pages

**4. JavaScript:**
```javascript
'/js/script.js'  // Main application logic
```

**5. Assets:**
```javascript
'/images/favicon.ico'
// Note: Add more images as needed
```

**6. External Dependencies:**
```javascript
'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css',
'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js',
'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js'
```

---

### Adding New Files to Cache

**When to Update:**
- New chapter added
- New quiz created
- New images added
- CSS/JS files added
- External library added/updated

**Procedure:**

1. **Edit `service-worker.js`:**
```javascript
const urlsToCache = [
  // ... existing files ...
  '/chapter13.html',  // ← Add new file
];
```

2. **Increment cache version:**
```javascript
const CACHE_NAME = 'ai-fluency-cache-v2';  // v1 → v2
```

3. **Deploy updated service worker**

4. **Test in browser:**
   - Open DevTools → Application → Service Workers
   - Click "Update" to force new SW installation
   - Verify new files are cached

**Important:** Always increment cache version when updating `urlsToCache`.

---

## Activation Phase

### Activate Event Handler

**Location:** `service-worker.js:127-140`

**Code:**
```javascript
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
```

### Purpose

**Activation** occurs when a new Service Worker is ready to take control. This phase is used for:
- Cleaning up old caches
- Migrating data
- Claiming control of pages

### Cache Cleanup Process

**Step-by-Step:**

1. **Define whitelist:**
```javascript
const cacheWhitelist = [CACHE_NAME];
// Only 'ai-fluency-cache-v2' is allowed
```

2. **Get all cache names:**
```javascript
caches.keys()
// Returns: ['ai-fluency-cache-v1', 'ai-fluency-cache-v2']
```

3. **Delete old caches:**
```javascript
cacheNames.map(cacheName => {
  if (cacheWhitelist.indexOf(cacheName) === -1) {
    return caches.delete(cacheName);
  }
})
// Deletes: 'ai-fluency-cache-v1'
// Keeps: 'ai-fluency-cache-v2'
```

### Why Cleanup Matters

**Storage Limits:**
- Browsers have storage quotas
- Old caches waste space
- Can lead to quota exceeded errors

**Cache Size Example:**
```
ai-fluency-cache-v1: 15 MB
ai-fluency-cache-v2: 16 MB
ai-fluency-cache-v3: 17 MB
─────────────────────────────
Total without cleanup: 48 MB
Total with cleanup: 17 MB (latest only)
```

---

## Fetch Handling

### Fetch Event Handler

**Location:** `service-worker.js:86-124`

**Purpose:** Intercepts all network requests and serves responses from cache or network.

**Full Code:**
```javascript
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
```

---

### Fetch Flow Diagram

```
┌──────────────────────────────────────────────────────────────┐
│                     FETCH EVENT FLOW                          │
└──────────────────────────────────────────────────────────────┘

Browser makes request (e.g., GET /chapter1.html)
              ↓
   ┌──────────────────────┐
   │  Fetch event fires   │
   └──────────┬───────────┘
              ↓
   ┌──────────────────────┐
   │  Check cache         │  caches.match(request)
   └──────────┬───────────┘
              ↓
        ┌─────┴─────┐
        ↓           ↓
   Found in      NOT Found
   Cache         in Cache
        ↓           ↓
   Return      Fetch from
   Cached      Network
   Response         ↓
        │      ┌─────┴─────┐
        │      ↓           ↓
        │   Success     Failure
        │      ↓           ↓
        │   Cache it   Return
        │   Return     offline.html
        │   Response   (if HTML)
        │      ↓           │
        └──────┴───────────┘
                ↓
         Browser receives response
```

---

### Cache Matching

**Code:**
```javascript
caches.match(event.request)
  .then(response => {
    if (response) {
      return response;  // Cache hit!
    }
    // Cache miss, continue to network
  });
```

**`caches.match(request)`:**
- **Parameters:** Request object
- **Returns:** `Promise<Response|undefined>`
- **Behavior:**
  - Searches all caches (unless scope is specified)
  - Returns first matching response
  - Returns `undefined` if no match

**Matching Rules:**
- URL must match exactly (including query strings)
- Method must match (GET, POST, etc.)
- Headers are NOT compared by default

**Example:**
```javascript
// These are DIFFERENT cache entries:
caches.match('/chapter1.html')
caches.match('/chapter1.html?v=2')
caches.match('/chapter1.html#section')  // Fragments ignored

// These match the SAME cache entry:
caches.match('/chapter1.html')
caches.match('/chapter1.html#intro')  // Fragment stripped
```

---

### Request Cloning

**Code:**
```javascript
const fetchRequest = event.request.clone();
```

**Why Clone?**
- Requests and responses can only be consumed once
- We need the request twice: once for fetch, once for caching
- Cloning creates an independent copy

**Without Cloning (WRONG):**
```javascript
// This FAILS because request is consumed twice
fetch(event.request)
  .then(response => {
    cache.put(event.request, response);  // ❌ Request already consumed
  });
```

**With Cloning (CORRECT):**
```javascript
const fetchRequest = event.request.clone();
fetch(fetchRequest)
  .then(response => {
    const responseToCache = response.clone();
    cache.put(event.request, responseToCache);  // ✅ Works!
    return response;
  });
```

---

### Response Validation

**Code:**
```javascript
if (!response || response.status !== 200 || response.type !== 'basic') {
  return response;
}
```

**Validation Checks:**

1. **`!response`** - Response exists
   - Network might return `null` on severe errors

2. **`response.status !== 200`** - HTTP status is OK
   - Don't cache 404, 500, etc.
   - Only cache successful responses

3. **`response.type !== 'basic'`** - Response is same-origin
   - `basic` - Same-origin response
   - `cors` - Cross-origin response (allowed, but limited headers)
   - `opaque` - Cross-origin, no-cors response (can't read anything)

**Why Check `type`?**
```javascript
// Same-origin (type: 'basic')
fetch('/chapter1.html')  // ✅ Cache this

// Cross-origin with CORS (type: 'cors')
fetch('https://api.example.com/data')  // ⚠️ Can cache if needed

// Cross-origin, no-cors (type: 'opaque')
fetch('https://external.com/script.js', { mode: 'no-cors' })  // ❌ Don't cache
```

**Current Implementation:** Only caches `basic` (same-origin) responses. External CDN resources are pre-cached during install phase.

---

### Runtime Caching

**Code:**
```javascript
// Clone the response
const responseToCache = response.clone();

// Add to cache
caches.open(CACHE_NAME)
  .then(cache => {
    cache.put(event.request, responseToCache);
  });

return response;
```

**Purpose:** Cache new resources that weren't in the initial `urlsToCache` list.

**How It Works:**
1. User requests a resource not in cache (e.g., new image)
2. Service Worker fetches from network
3. Response is cloned
4. Original response sent to browser immediately
5. Cloned response saved to cache asynchronously
6. Next request for same resource served from cache

**Example Scenario:**
```
First request:  /images/new-diagram.png
  → Not in cache
  → Fetch from network
  → Cache response
  → Serve to browser

Second request: /images/new-diagram.png
  → Found in cache!
  → Serve instantly
  → No network request
```

**Benefits:**
- Improves performance on repeat visits
- Automatically builds cache over time
- No need to pre-cache every possible resource

---

### Offline Fallback

**Code:**
```javascript
.catch(error => {
  // Network request failed, show offline page for HTML requests
  if (event.request.headers.get('accept').includes('text/html')) {
    return caches.match('/offline.html');
  }
});
```

**Trigger Conditions:**
- Network unavailable (airplane mode)
- Server down
- DNS failure
- Timeout

**Behavior:**
- **HTML requests** → Show custom offline page
- **Other requests** (CSS, JS, images) → Fail silently

**Why Check `text/html`?**
```javascript
// Navigation request (user clicked link or typed URL)
Accept: text/html,application/xhtml+xml...
→ Show offline.html

// Image request
Accept: image/webp,image/apng,image/*
→ Let it fail (no offline page)

// API request
Accept: application/json
→ Let it fail (no offline page)
```

---

### Offline Page

**File:** `/offline.html`

**Purpose:** Custom page shown when user is offline and requests an uncached page.

**Implementation:**
```html
<!DOCTYPE html>
<html>
<head>
  <title>Offline - AI Fluency</title>
  <style>
    body {
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
      font-family: Arial, sans-serif;
      text-align: center;
      background-color: #F9F9FF;
    }
    .offline-content {
      max-width: 500px;
      padding: 2rem;
    }
    h1 { color: #4B6EFB; }
  </style>
</head>
<body>
  <div class="offline-content">
    <h1>📡 You're Offline</h1>
    <p>It looks like you've lost your internet connection.</p>
    <p>Please check your connection and try again.</p>
    <button onclick="window.location.reload()">Retry</button>
  </div>
</body>
</html>
```

**Important:** `offline.html` MUST be in the initial `urlsToCache` array.

---

## Caching Strategy

### Cache-First Strategy

**Current Implementation:** Cache-First with Network Fallback

**Flow:**
```
Request → Check Cache → Found? → Return cached response
                      ↓ Not Found
                   Fetch from Network → Cache response → Return
                      ↓ Network Fails
                   Return offline.html (HTML only)
```

**Pros:**
- ✅ Fastest possible load times
- ✅ Works completely offline
- ✅ Reduces bandwidth usage
- ✅ Lower server costs

**Cons:**
- ❌ May serve stale content
- ❌ Users don't see updates until cache version changes
- ❌ Requires manual cache version bumps

**Best For:**
- Static content (HTML, CSS, JS)
- Educational content that doesn't change frequently
- Progressive Web Apps
- **Our use case:** AI Fluency course content

---

### Alternative Strategies

**(Not currently implemented, but documented for future reference)**

#### 1. Network-First Strategy

**Flow:**
```
Request → Fetch from Network → Success? → Cache & Return
                             ↓ Fails
                          Check Cache → Return cached or fail
```

**Best For:**
- Frequently updated content
- News sites
- Social media feeds
- Live data

**Implementation:**
```javascript
self.addEventListener('fetch', event => {
  event.respondWith(
    fetch(event.request)
      .then(response => {
        const responseToCache = response.clone();
        caches.open(CACHE_NAME).then(cache => {
          cache.put(event.request, responseToCache);
        });
        return response;
      })
      .catch(() => {
        return caches.match(event.request);
      })
  );
});
```

---

#### 2. Stale-While-Revalidate

**Flow:**
```
Request → Return cached response immediately
       └→ Fetch from network in background
       └→ Update cache for next request
```

**Best For:**
- Content that changes occasionally
- Non-critical updates
- Balancing speed and freshness

**Implementation:**
```javascript
self.addEventListener('fetch', event => {
  event.respondWith(
    caches.match(event.request).then(cachedResponse => {
      const fetchPromise = fetch(event.request).then(networkResponse => {
        caches.open(CACHE_NAME).then(cache => {
          cache.put(event.request, networkResponse.clone());
        });
        return networkResponse;
      });
      return cachedResponse || fetchPromise;
    })
  );
});
```

---

#### 3. Network-Only

**Flow:**
```
Request → Fetch from Network → Return (don't cache)
```

**Best For:**
- Real-time data
- User-specific data
- POST requests
- Admin APIs

**Implementation:**
```javascript
self.addEventListener('fetch', event => {
  if (event.request.url.includes('/api/')) {
    event.respondWith(fetch(event.request));
  }
});
```

---

#### 4. Cache-Only

**Flow:**
```
Request → Check Cache → Return cached or fail
```

**Best For:**
- Known resources
- Testing
- Rare use case

**Implementation:**
```javascript
self.addEventListener('fetch', event => {
  event.respondWith(caches.match(event.request));
});
```

---

### Strategy Comparison Table

| Strategy | Speed | Freshness | Offline | Best For |
|----------|-------|-----------|---------|----------|
| **Cache-First** (Current) | ⭐⭐⭐⭐⭐ | ⭐ | ✅ Yes | Static content, PWAs |
| **Network-First** | ⭐⭐ | ⭐⭐⭐⭐⭐ | ⚠️ Partial | Dynamic content |
| **Stale-While-Revalidate** | ⭐⭐⭐⭐ | ⭐⭐⭐ | ✅ Yes | Semi-dynamic content |
| **Network-Only** | ⭐⭐ | ⭐⭐⭐⭐⭐ | ❌ No | Real-time data |
| **Cache-Only** | ⭐⭐⭐⭐⭐ | ⭐ | ✅ Yes | Known static resources |

---

## Cache Management

### Inspecting Caches

**Chrome DevTools:**
1. Open DevTools (F12)
2. Go to **Application** tab
3. Expand **Cache Storage** in sidebar
4. Click cache name (e.g., `ai-fluency-cache-v1`)
5. View all cached resources

**Firefox DevTools:**
1. Open DevTools (F12)
2. Go to **Storage** tab
3. Expand **Cache Storage**
4. Click cache name

**Programmatic Access:**
```javascript
// List all caches
caches.keys().then(cacheNames => {
  console.log('Caches:', cacheNames);
});

// View cache contents
caches.open('ai-fluency-cache-v1').then(cache => {
  cache.keys().then(requests => {
    console.log('Cached URLs:', requests.map(req => req.url));
  });
});

// Get cache size (approximate)
navigator.storage.estimate().then(estimate => {
  console.log(`Using ${estimate.usage} of ${estimate.quota} bytes`);
});
```

---

### Manual Cache Updates

**Via DevTools:**
1. Application → Service Workers
2. Check "Update on reload"
3. Reload page

**Programmatic Update:**
```javascript
// Force update
navigator.serviceWorker.ready.then(registration => {
  registration.update();
});
```

**Clear All Caches:**
```javascript
caches.keys().then(cacheNames => {
  return Promise.all(
    cacheNames.map(cacheName => caches.delete(cacheName))
  );
}).then(() => {
  console.log('All caches cleared');
});
```

---

### Cache Storage Limits

**Browser Quotas:**

| Browser | Storage Type | Quota |
|---------|-------------|-------|
| Chrome | Total storage | ~60% of disk space |
| Firefox | Per-origin | Min 50MB, up to available space |
| Safari | Per-origin | 50MB default, 1GB with prompt |
| Edge | Total storage | ~60% of disk space |

**Checking Quota:**
```javascript
navigator.storage.estimate().then(estimate => {
  const percentUsed = (estimate.usage / estimate.quota * 100).toFixed(2);
  console.log(`Storage: ${estimate.usage} / ${estimate.quota} bytes (${percentUsed}%)`);
});
```

**What Happens When Quota Exceeded?**
- Cache write operations fail silently
- Service Worker installation may fail
- Browser may evict old caches (LRU - Least Recently Used)

**Prevention:**
- Keep cache size reasonable
- Clean up old caches during activation
- Use selective caching (don't cache everything)

---

## Service Worker Registration

### Registration Code

**Location:** Every HTML file `<head>` section

**Code:**
```html
<!-- Service Worker Registration -->
<script>
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('/service-worker.js')
        .then(registration => {
          console.log('Service Worker registered with scope:', registration.scope);
        })
        .catch(error => {
          console.error('Service Worker registration failed:', error);
        });
    });
  }
</script>
```

---

### Registration Breakdown

**1. Feature Detection:**
```javascript
if ('serviceWorker' in navigator) {
  // Browser supports Service Workers
}
```

**Why?**
- Not all browsers support Service Workers
- Prevents errors in older browsers
- Progressive enhancement approach

**Supported Browsers:**
- ✅ Chrome 40+
- ✅ Firefox 44+
- ✅ Safari 11.1+
- ✅ Edge 17+
- ❌ IE (any version)

---

**2. Wait for Page Load:**
```javascript
window.addEventListener('load', () => {
  // Register after page load
});
```

**Why?**
- Prevents Service Worker from competing with main thread
- Ensures page loads quickly
- Service Worker registration is non-blocking

---

**3. Register Service Worker:**
```javascript
navigator.serviceWorker.register('/service-worker.js')
```

**Parameters:**
- `scriptURL` (required) - Path to service worker file
- `options` (optional) - Configuration object

**Options:**
```javascript
navigator.serviceWorker.register('/service-worker.js', {
  scope: '/',  // Default: directory of SW file
  type: 'classic',  // or 'module' for ES modules
  updateViaCache: 'imports'  // Cache behavior
});
```

---

**4. Handle Success:**
```javascript
.then(registration => {
  console.log('Service Worker registered with scope:', registration.scope);
})
```

**`registration` Object Properties:**
- `scope` - URL scope of Service Worker
- `installing` - Service Worker being installed
- `waiting` - Service Worker waiting to activate
- `active` - Currently active Service Worker
- `updatefound` - Event for updates

---

**5. Handle Failure:**
```javascript
.catch(error => {
  console.error('Service Worker registration failed:', error);
})
```

**Common Errors:**
- **"SecurityError"** - Not served over HTTPS
- **"NotSupportedError"** - Browser doesn't support SW
- **"NetworkError"** - Can't fetch service-worker.js
- **"TypeError"** - Invalid SW file path

---

### Registration Lifecycle

```
Page loads
    ↓
Wait for 'load' event
    ↓
navigator.serviceWorker.register()
    ↓
Browser fetches /service-worker.js
    ↓
┌───────┴────────┐
│   Success?     │
└───────┬────────┘
    ↓           ↓
  Yes          No
    ↓           ↓
Install      Log error
Activate     Stop
Control pages
```

---

### Checking Registration Status

**Programmatically:**
```javascript
navigator.serviceWorker.getRegistration().then(registration => {
  if (registration) {
    console.log('Service Worker is registered');
    console.log('Scope:', registration.scope);
    console.log('Active:', registration.active);
  } else {
    console.log('No Service Worker registered');
  }
});
```

**Via DevTools:**
1. Chrome: DevTools → Application → Service Workers
2. Firefox: DevTools → Application → Service Workers
3. Shows: Status, Scope, Source, Controls

---

## Web App Manifest

### Manifest File

**Location:** `/manifest.json`

**Purpose:** Defines PWA metadata for installation

**Full Content:**
```json
{
  "name": "AI Fluency Course",
  "short_name": "AI Fluency",
  "description": "Learn about artificial intelligence concepts and applications",
  "start_url": "/index.html",
  "display": "standalone",
  "background_color": "#F9F9FF",
  "theme_color": "#4B6EFB",
  "id": "/index.html",

  "icons": [
    {
      "src": "/images/android-chrome-192x192.png",
      "sizes": "192x192",
      "type": "image/png",
      "purpose": "any maskable"
    },
    {
      "src": "/images/android-chrome-512x512.png",
      "sizes": "512x512",
      "type": "image/png",
      "purpose": "any maskable"
    }
  ],

  "screenshots": [
    {
      "src": "/images/screenshot-desktop.png",
      "sizes": "1280x720",
      "type": "image/png",
      "form_factor": "wide"
    },
    {
      "src": "/images/screenshot-mobile.png",
      "sizes": "390x844",
      "type": "image/png"
    }
  ]
}
```

---

### Manifest Properties

#### Essential Properties

**`name`** (required)
- Full app name shown during installation
- Example: "AI Fluency Course"

**`short_name`** (required)
- Short name for home screen (max 12 characters)
- Example: "AI Fluency"

**`start_url`** (required)
- URL that opens when app launches
- Should be relative or absolute
- Example: "/index.html"

**`display`** (required)
- How the app should be displayed

**Options:**
```json
"fullscreen"  // No browser UI (games)
"standalone"  // App-like (our choice)
"minimal-ui"  // Minimal browser UI
"browser"     // Normal browser tab
```

**`icons`** (required)
- Array of icon objects
- Minimum sizes: 192x192 and 512x512

---

#### Visual Properties

**`background_color`**
- Splash screen background
- Hex color code
- Example: "#F9F9FF"

**`theme_color`**
- Toolbar color
- Affects status bar on mobile
- Example: "#4B6EFB" (primary blue)

**`description`**
- Brief app description
- Shown in app stores/install prompts

---

#### Icon Configuration

**Icon Sizes:**
```json
"icons": [
  {
    "src": "/images/android-chrome-192x192.png",
    "sizes": "192x192",
    "type": "image/png",
    "purpose": "any maskable"
  },
  {
    "src": "/images/android-chrome-512x512.png",
    "sizes": "512x512",
    "type": "image/png",
    "purpose": "any maskable"
  }
]
```

**Purpose Options:**
- `any` - Standard icon
- `maskable` - Safe area for Android adaptive icons
- `any maskable` - Supports both (recommended)

**Required Sizes:**
- **192x192** - Minimum for Android
- **512x512** - High-resolution displays

**Recommended Additional Sizes:**
- 72x72, 96x96, 128x128, 144x144, 152x152, 180x180, 384x384

---

#### Screenshots

**Purpose:** Shown in browser installation prompt

```json
"screenshots": [
  {
    "src": "/images/screenshot-desktop.png",
    "sizes": "1280x720",
    "type": "image/png",
    "form_factor": "wide"
  },
  {
    "src": "/images/screenshot-mobile.png",
    "sizes": "390x844",
    "type": "image/png"
  }
]
```

**Form Factors:**
- `wide` - Desktop/laptop screenshots (16:9 or 16:10)
- (none) - Mobile/tablet screenshots (portrait)

**Recommendations:**
- Use high-quality screenshots
- Show key features
- Keep UI clean
- Max 8 screenshots

---

### Linking Manifest

**HTML `<head>` section:**
```html
<!-- Manifest and Icons -->
<link rel="manifest" href="/manifest.json">
<link rel="apple-touch-icon" href="/images/apple-touch-icon.png">

<!-- PWA Meta Tags -->
<meta name="theme-color" content="#4B6EFB">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="AI Fluency">
```

**iOS-Specific:**
- `apple-touch-icon` - Icon for iOS home screen (180x180)
- `apple-mobile-web-app-capable` - Enables standalone mode
- `apple-mobile-web-app-status-bar-style` - Status bar style
- `apple-mobile-web-app-title` - App name on iOS

---

## Testing & Debugging

### Chrome DevTools

**Service Worker Panel:**
1. Open DevTools (F12)
2. Go to **Application** tab
3. Click **Service Workers** in sidebar

**Features:**
- ✅ View registration status
- ✅ See active/waiting/installing Service Workers
- ✅ Update/Unregister buttons
- ✅ Bypass for network checkbox
- ✅ Update on reload checkbox
- ✅ Offline simulation

---

**Cache Storage Panel:**
1. Application tab → **Cache Storage**
2. View all caches
3. Inspect cached resources
4. Delete individual items
5. Clear entire cache

---

### Testing Checklist

#### 1. Installation Test

**Steps:**
1. Open site in incognito window
2. Open DevTools → Application → Service Workers
3. Verify "Status: activated and running"
4. Check Cache Storage for new cache
5. Verify all resources cached

**Expected Result:**
```
✅ Service Worker: activated and running
✅ Scope: https://scibono.co.za/
✅ Cache: ai-fluency-cache-v1 (70+ items)
```

---

#### 2. Offline Test

**Steps:**
1. Load site normally (online)
2. Enable "Offline" in DevTools Network tab
3. Navigate to different pages
4. Click links
5. Reload pages

**Expected Result:**
```
✅ Cached pages load instantly
✅ Uncached pages show offline.html
✅ No network errors in console
```

---

#### 3. Update Test

**Steps:**
1. Change content in a chapter file
2. Increment cache version in service-worker.js
3. Upload files
4. Reload site
5. Verify new Service Worker installing
6. Close all tabs
7. Reopen site
8. Verify updated content appears

**Expected Result:**
```
✅ New Service Worker installed
✅ Old cache deleted
✅ New cache created
✅ Updated content visible
```

---

#### 4. Install Prompt Test

**Chrome (Desktop/Android):**
1. Open site in new browser profile
2. Wait for install banner
3. Click "Install"
4. Verify app appears in app drawer/start menu

**Expected Result:**
```
✅ Install prompt appears
✅ App installs successfully
✅ App opens in standalone window
✅ No browser UI visible
```

---

### Common Issues

| Issue | Symptom | Solution |
|-------|---------|----------|
| **SW not registering** | No SW in DevTools | Check HTTPS, verify file path |
| **Install fails** | Red error in console | Check one URL in urlsToCache isn't 404 |
| **Updates not showing** | Old content persists | Increment CACHE_NAME, force update |
| **Offline page not working** | Network error offline | Ensure /offline.html in urlsToCache |
| **Install prompt not showing** | No install button | Check manifest.json, wait 30 seconds |

---

### Debugging Commands

**Console Commands:**
```javascript
// Check if SW is registered
navigator.serviceWorker.controller
// Returns: ServiceWorker object or null

// Get registration
navigator.serviceWorker.getRegistration()
  .then(reg => console.log(reg));

// Force update
navigator.serviceWorker.ready
  .then(reg => reg.update());

// Unregister (for testing)
navigator.serviceWorker.getRegistration()
  .then(reg => reg.unregister());

// Clear all caches
caches.keys().then(keys =>
  Promise.all(keys.map(key => caches.delete(key)))
);

// Check cache contents
caches.open('ai-fluency-cache-v1')
  .then(cache => cache.keys())
  .then(keys => console.log(keys.map(k => k.url)));
```

---

## Update Procedures

### When to Update Service Worker

**Update Triggers:**
- ✅ Content changes (new chapter, updated quiz)
- ✅ CSS/JS changes
- ✅ New features added
- ✅ Bug fixes in caching logic
- ✅ Adding new files to cache
- ❌ NOT needed for: Minor text edits, analytics changes

---

### Update Process

**Step 1: Make Code Changes**
```javascript
// Example: Add new chapter
const urlsToCache = [
  // ... existing files ...
  '/chapter14.html',  // ← New file
];
```

**Step 2: Increment Cache Version**
```javascript
// Before
const CACHE_NAME = 'ai-fluency-cache-v1';

// After
const CACHE_NAME = 'ai-fluency-cache-v2';
```

**Step 3: Deploy Files**
```bash
# Upload updated files to server
scp service-worker.js user@server:/var/www/html/sci-bono-aifluency/
scp chapter14.html user@server:/var/www/html/sci-bono-aifluency/
```

**Step 4: Test Locally**
```
1. Open DevTools → Application → Service Workers
2. Check "Update on reload"
3. Reload page
4. Verify new Service Worker installing
5. Check new cache created
6. Verify old cache deleted after activation
```

**Step 5: Deploy to Production**
```
1. Push to production server
2. Monitor error logs
3. Test on different devices
4. Verify users see updates
```

---

### User Experience During Update

**Scenario 1: Page Reload**
```
User reloads page
    ↓
Browser checks for new SW
    ↓
New SW downloads
    ↓
New SW installs in background
    ↓
User sees OLD content (from old cache)
    ↓
User closes all tabs
    ↓
User reopens site
    ↓
New SW activates
    ↓
User sees NEW content
```

**Scenario 2: Skip Waiting (Advanced)**
```javascript
// In service-worker.js install event
self.addEventListener('install', event => {
  self.skipWaiting();  // Activate immediately
  // ... rest of install logic
});

// In activate event
self.addEventListener('activate', event => {
  return self.clients.claim();  // Take control immediately
});
```

**Effect:** Users see updates immediately, but may cause issues if page HTML and cached resources are out of sync.

---

### Versioning Strategy

**Semantic Versioning:**
```javascript
// Major changes (new course, redesign)
const CACHE_NAME = 'ai-fluency-cache-v2';

// Minor changes (new chapters, updates)
const CACHE_NAME = 'ai-fluency-cache-v2.1';

// Patches (bug fixes, small updates)
const CACHE_NAME = 'ai-fluency-cache-v2.1.1';
```

**Date-Based Versioning:**
```javascript
const CACHE_NAME = 'ai-fluency-cache-2025-01-15';
```

**Content Hash Versioning:**
```javascript
// Generated by build tool
const CACHE_NAME = 'ai-fluency-cache-a3d4f2c';
```

---

## Troubleshooting

### Issue: Service Worker Not Registering

**Symptoms:**
- No Service Worker shown in DevTools
- Console error: "SecurityError" or "NotSupportedError"

**Causes & Solutions:**

**1. Not Using HTTPS**
```
Error: SecurityError
Solution: Deploy to HTTPS or test on localhost
```

**2. Wrong File Path**
```
Error: NetworkError
Solution: Verify /service-worker.js exists at root
```

**3. Browser Doesn't Support SW**
```
Error: undefined
Solution: Test in Chrome/Firefox/Edge, not IE
```

---

### Issue: Content Not Updating

**Symptoms:**
- Old content appears after deployment
- New chapters not showing

**Solutions:**

**1. Increment Cache Version**
```javascript
// Change from v1 to v2
const CACHE_NAME = 'ai-fluency-cache-v2';
```

**2. Force Update in DevTools**
```
Application → Service Workers → Update
```

**3. Clear Cache Manually**
```
Application → Cache Storage → Right-click → Delete
```

**4. Hard Refresh**
```
Ctrl+Shift+R (Chrome/Firefox)
Cmd+Shift+R (Mac)
```

---

### Issue: Offline Page Not Showing

**Symptoms:**
- "No internet" dinosaur page appears
- Generic browser error offline

**Solutions:**

**1. Verify offline.html in Cache**
```javascript
const urlsToCache = [
  '/offline.html',  // ← Must be here
  // ...
];
```

**2. Check Fetch Handler**
```javascript
// Ensure this code exists
.catch(error => {
  if (event.request.headers.get('accept').includes('text/html')) {
    return caches.match('/offline.html');
  }
});
```

---

### Issue: Install Prompt Not Appearing

**Symptoms:**
- No "Install" button
- No install banner

**Solutions:**

**1. Check PWA Requirements**
```
✅ Served over HTTPS
✅ Has valid manifest.json
✅ Has Service Worker registered
✅ Has icons (192x192, 512x512)
✅ User has not dismissed prompt before
✅ User has not installed app already
```

**2. Wait 30 Seconds**
```
Chrome waits to ensure user is engaged
```

**3. Test in Incognito**
```
Open in private/incognito window
```

**4. Check Manifest Errors**
```
DevTools → Console → Look for manifest errors
```

---

### Issue: High Cache Size

**Symptoms:**
- Storage quota warnings
- Slow installation
- Cache operations failing

**Solutions:**

**1. Remove Unnecessary Files**
```javascript
// Don't cache everything
const urlsToCache = [
  // Remove: Large videos, fonts, unused files
];
```

**2. Use Selective Caching**
```javascript
// Only cache certain file types
if (url.match(/\.(js|css|html)$/)) {
  cache.put(request, response);
}
```

**3. Implement Cache Expiration**
```javascript
// Delete old entries
const maxAgeSeconds = 7 * 24 * 60 * 60; // 1 week
```

---

## Browser Compatibility

### Service Worker Support

| Browser | Version | Support Level |
|---------|---------|---------------|
| Chrome | 40+ | ✅ Full |
| Firefox | 44+ | ✅ Full |
| Safari | 11.1+ | ✅ Full |
| Edge (Chromium) | 79+ | ✅ Full |
| Edge (Legacy) | 17-18 | ⚠️ Limited |
| Opera | 27+ | ✅ Full |
| Samsung Internet | 4.0+ | ✅ Full |
| IE | Any | ❌ Not supported |

### PWA Install Support

| Platform | Browser | Install Method |
|----------|---------|----------------|
| **Android** | Chrome | ✅ beforeinstallprompt |
| **Android** | Firefox | ✅ Manual (Add to Home Screen) |
| **Android** | Samsung | ✅ beforeinstallprompt |
| **iOS** | Safari | ⚠️ Manual only (Share → Add to Home Screen) |
| **Windows** | Chrome/Edge | ✅ beforeinstallprompt |
| **macOS** | Chrome/Edge | ✅ beforeinstallprompt |
| **macOS** | Safari | ⚠️ Limited support |
| **Linux** | Chrome/Firefox | ✅ beforeinstallprompt |

### Feature Compatibility

| Feature | Chrome | Firefox | Safari | Edge |
|---------|--------|---------|--------|------|
| Service Worker | ✅ 40+ | ✅ 44+ | ✅ 11.1+ | ✅ 17+ |
| Cache API | ✅ 43+ | ✅ 39+ | ✅ 11.1+ | ✅ 16+ |
| beforeinstallprompt | ✅ Yes | ❌ No | ❌ No | ✅ Yes |
| Web App Manifest | ✅ Yes | ✅ Yes | ✅ Yes | ✅ Yes |
| Background Sync | ✅ Yes | ❌ No | ❌ No | ✅ Yes |
| Push Notifications | ✅ Yes | ✅ Yes | ❌ No | ✅ Yes |

---

## Performance Optimization

### Caching Best Practices

**1. Pre-cache Critical Resources Only**
```javascript
// Good: Essential files only
const urlsToCache = [
  '/',
  '/css/styles.css',
  '/js/script.js'
];

// Bad: Everything including large files
const urlsToCache = [
  '/videos/intro.mp4',  // 50MB video!
  '/images/hero-4k.jpg'  // 8MB image!
];
```

**2. Use Runtime Caching for Secondary Resources**
```javascript
// Let images/videos cache on first access
// Don't pre-cache them
```

**3. Implement Cache Limits**
```javascript
// Limit cache size (future enhancement)
const MAX_CACHE_SIZE = 50 * 1024 * 1024; // 50MB

function trimCache(cacheName, maxSize) {
  caches.open(cacheName).then(cache => {
    // Implement size checking and old entry removal
  });
}
```

---

### Network Performance

**1. Use CDN for External Resources**
```javascript
// Already implemented
'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css'
```

**2. Enable HTTP/2**
```
Server configuration (already enabled on most hosting)
```

**3. Compress Responses**
```apache
# .htaccess
<IfModule mod_deflate.c>
  AddOutputFilterByType DEFLATE text/html text/css application/javascript
</IfModule>
```

---

### Service Worker Performance

**1. Keep Service Worker Lightweight**
```
Current: 140 lines ✅
Recommended: < 1000 lines
```

**2. Avoid Synchronous Operations**
```javascript
// Bad: Blocks SW thread
let data = getDataSync();

// Good: Non-blocking
getDataAsync().then(data => { /* ... */ });
```

**3. Use `event.waitUntil()` Properly**
```javascript
// Ensure promises complete
event.waitUntil(
  cache.addAll(urls)  // Wait for caching to complete
);
```

---

## Security Considerations

### HTTPS Requirement

**Why HTTPS is Required:**
- Service Workers can intercept ALL network requests
- Could be used for man-in-the-middle attacks
- HTTPS prevents SW injection

**Development Exception:**
- `localhost` and `127.0.0.1` work without HTTPS
- Testing purposes only

---

### Content Security Policy

**Recommended CSP Header:**
```
Content-Security-Policy:
  default-src 'self';
  script-src 'self' https://cdnjs.cloudflare.com;
  style-src 'self' https://cdnjs.cloudflare.com;
  img-src 'self' data:;
  connect-src 'self';
  worker-src 'self';
```

**Service Worker Specific:**
```
worker-src 'self';  # Allow SW from same origin only
```

---

### Cache Security

**Don't Cache Sensitive Data:**
```javascript
// Don't cache:
- User tokens
- Personal information
- API responses with user data
- Payment information
```

**Validate Cached Responses:**
```javascript
// Check response status before caching
if (response.status === 200) {
  cache.put(request, response);
}
```

---

## Future Enhancements

### Background Sync

**Purpose:** Sync data when connection is restored

**Implementation:**
```javascript
// Register sync
self.addEventListener('sync', event => {
  if (event.tag === 'quiz-submission') {
    event.waitUntil(syncQuizzes());
  }
});

function syncQuizzes() {
  // Send queued quiz submissions to server
  return getQuizzesFromIndexedDB()
    .then(quizzes => {
      return Promise.all(
        quizzes.map(quiz => fetch('/api/quizzes', {
          method: 'POST',
          body: JSON.stringify(quiz)
        }))
      );
    });
}
```

---

### Push Notifications

**Purpose:** Notify users of new content

**Implementation:**
```javascript
// Request permission
Notification.requestPermission().then(permission => {
  if (permission === 'granted') {
    // Subscribe to push
    registration.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: publicKey
    });
  }
});

// Handle push events
self.addEventListener('push', event => {
  const data = event.data.json();
  self.registration.showNotification(data.title, {
    body: data.message,
    icon: '/images/icon-192x192.png'
  });
});
```

---

### Advanced Caching Strategies

**Stale-While-Revalidate for Chapters:**
```javascript
if (request.url.includes('chapter')) {
  event.respondWith(
    caches.open(CACHE_NAME).then(cache => {
      return cache.match(request).then(cachedResponse => {
        const fetchPromise = fetch(request).then(networkResponse => {
          cache.put(request, networkResponse.clone());
          return networkResponse;
        });
        return cachedResponse || fetchPromise;
      });
    })
  );
}
```

---

### IndexedDB Integration

**Purpose:** Store structured data (quiz progress, bookmarks)

**Implementation:**
```javascript
// Open database
const dbPromise = indexedDB.open('ai-fluency-db', 1);

dbPromise.onupgradeneeded = event => {
  const db = event.target.result;
  db.createObjectStore('progress', { keyPath: 'chapterId' });
};

// Store progress
function saveProgress(chapterId, progress) {
  return dbPromise.then(db => {
    const tx = db.transaction('progress', 'readwrite');
    tx.objectStore('progress').put({
      chapterId: chapterId,
      completed: progress.completed,
      timestamp: Date.now()
    });
    return tx.complete;
  });
}
```

---

## Related Documents

### Technical Documentation
- [JavaScript API Reference](javascript-api.md) - Client-side code documentation
- [Current Architecture](../01-Architecture/current-architecture.md) - System overview
- [Future Architecture](../01-Architecture/future-architecture.md) - Planned LMS features

### Development Guides
- [Development Setup](../04-Development/setup-guide.md) (coming soon) - Local environment
- [Testing Procedures](../04-Development/testing-procedures.md) (coming soon) - QA guide

### External Resources
- [MDN: Service Worker API](https://developer.mozilla.org/en-US/docs/Web/API/Service_Worker_API)
- [Google: Service Worker Guide](https://developers.google.com/web/fundamentals/primers/service-workers)
- [Service Worker Cookbook](https://serviceworke.rs/) - Code examples
- [PWA Builder](https://www.pwabuilder.com/) - PWA testing tool

---

## Document Change Log

| Date | Version | Author | Changes |
|------|---------|--------|---------|
| 2025-10-27 | 1.0 | Dev Team | Initial Service Worker implementation guide |

---

**END OF DOCUMENT**

*This Service Worker guide documents the complete PWA implementation for the Sci-Bono AI Fluency platform. Use this as a reference for understanding, testing, and maintaining the offline-capable Progressive Web App functionality.*
