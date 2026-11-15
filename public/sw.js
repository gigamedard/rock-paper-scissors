const CACHE_NAME = 'battlepool-shell-v8';
const DYNAMIC_CACHE_NAME = 'battlepool-dynamic-v8';
const I18N_VERSION = '6';

// App shell local : pré-caché en priorité (aucun domaine externe ici, sinon
// `cache.addAll` échoue sur réseau mobile faible et le SW ne s'installe jamais).
const LOCAL_STATIC_ASSETS = [
  '/',
  '/manifest.json',
  '/css/style.css',
  '/icon.svg',
  '/pwa_icon_192.png',
  '/pwa_icon_512.png',
  '/favicon.ico',
  `/locales/fr.json?v=${I18N_VERSION}`,
  `/locales/en.json?v=${I18N_VERSION}`
];

// Optionnels : mis en cache en arrière-plan, sans bloquer l'installation.
// Bundle applicatif à noms fixes (Vite) : pré-caché pour un lancement PWA
// quasi instantané, même sur réseau mobile faible.
const APP_ASSETS = [
  '/build/assets/app.js',
  '/build/assets/app.css',
  '/build/assets/marketplace.css',
  '/build/assets/referral.css',
  '/build/assets/influencer.css'
];

const OPTIONAL_EXTERNAL_ASSETS = [
  'https://fonts.googleapis.com/css2?family=Orbitron:wght@700;800;900&family=Rajdhani:wght@500;600;700&display=swap'
];

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

function timeout(ms) {
  return new Promise((resolve) => setTimeout(() => resolve(null), ms));
}

async function putInCache(cacheName, request, response) {
  try {
    if (response && response.status === 200) {
      const cache = await caches.open(cacheName);
      await cache.put(request, response.clone());
    }
  } catch (e) { /* quota/opaque : noop */ }
}

/**
 * Network-first avec délai court : sur réseau mobile faible, on ne bloque pas
 * l'ouverture de la PWA ; on sert le cache après `timeoutMs` et la réponse
 * réseau continue en tâche de fond pour mettre à jour le cache.
 */
async function networkFirst(request, cacheName, timeoutMs, fallbackRoot = false) {
  const cache = await caches.open(cacheName);
  const networkPromise = fetch(request)
    .then((response) => {
      putInCache(cacheName, request, response);
      return response;
    })
    .catch(() => null);

  const early = await Promise.race([networkPromise, timeout(timeoutMs)]);
  if (early) return early;

  const cached = (await cache.match(request)) || (fallbackRoot ? await caches.match('/') : null);
  if (cached) return cached;

  // Pas de cache : on attend la fin du réseau (premier chargement).
  const late = await networkPromise;
  return late || new Response('Réseau indisponible', { status: 503, statusText: 'Service Unavailable' });
}

/** Cache-first + revalidation en arrière-plan (assets statiques/locales). */
async function staleWhileRevalidate(request, cacheName) {
  const cache = await caches.open(cacheName);
  const cached = await cache.match(request);
  const networkPromise = fetch(request)
    .then((response) => {
      putInCache(cacheName, request, response);
      return response;
    })
    .catch(() => null);

  if (cached) return cached;
  const network = await networkPromise;
  return network || new Response('Asset indisponible hors ligne', { status: 503, statusText: 'Service Unavailable' });
}

self.addEventListener('install', (event) => {
  event.waitUntil((async () => {
    const cache = await caches.open(CACHE_NAME);
    const dynamic = await caches.open(DYNAMIC_CACHE_NAME);
    // Chaque asset local est indépendant : un 404/erreur ne bloque pas le SW.
    await Promise.allSettled([
      ...LOCAL_STATIC_ASSETS.map((url) => cache.add(url)),
      ...APP_ASSETS.map((url) => dynamic.add(url))
    ]);
    // Optionnels en arrière-plan (ne retarde pas l'installation).
    OPTIONAL_EXTERNAL_ASSETS.forEach((url) => { cache.add(url).catch(() => {}); });
    await self.skipWaiting();
  })());
});

self.addEventListener('activate', (event) => {
  event.waitUntil((async () => {
    const keys = await caches.keys();
    await Promise.all(keys.map((key) => {
      if (key !== CACHE_NAME && key !== DYNAMIC_CACHE_NAME) {
        return caches.delete(key);
      }
      return null;
    }));
    await self.clients.claim();
  })());
});

self.addEventListener('fetch', (event) => {
  const request = event.request;
  const url = new URL(request.url);

  if (request.method !== 'GET') return;
  if (!request.url.startsWith(self.location.origin) && !request.url.startsWith('http')) return;

  const isExcluded = EXCLUDED_PATHS.some((path) => url.pathname.includes(path));
  if (isExcluded) return;

  // Navigation HTML : CACHE-FIRST + revalidation en arrière-plan.
  // Sur réseau mobile faible, la PWA s'ouvre immédiatement depuis le cache.
  // Une nouvelle version est prise via le cycle SW (CACHE_NAME bump + update
  // + reload dans pwa.js), donc pas de risque de bloquer un vieux bundle.
  if (request.mode === 'navigate' || url.pathname === '/' || url.pathname === '/dashboard' || url.pathname === '/autoplay') {
    event.respondWith(staleWhileRevalidate(request, CACHE_NAME));
    return;
  }

  // Code applicatif Vite (noms fixes) : CACHE-FIRST + revalidation.
  // Cache invalidé à chaque déploiement via CACHE_NAME/DYNAMIC_CACHE_NAME.
  const isAppCode = url.pathname.includes('/build/assets/') || url.pathname === '/css/style.css';
  if (isAppCode) {
    event.respondWith(staleWhileRevalidate(request, DYNAMIC_CACHE_NAME));
    return;
  }

  // Locales / manifest : cache-first + revalidation (démarrage i18n instantané).
  const isLocaleOrManifest = url.pathname.startsWith('/locales/') || url.pathname === '/manifest.json';
  if (isLocaleOrManifest) {
    event.respondWith(staleWhileRevalidate(request, DYNAMIC_CACHE_NAME));
    return;
  }

  // Assets statiques et CDN : cache-first.
  const isStaticAsset =
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
    event.respondWith(staleWhileRevalidate(request, DYNAMIC_CACHE_NAME));
    return;
  }

  // Défaut : réseau + cache de secours.
  event.respondWith(networkFirst(request, DYNAMIC_CACHE_NAME, 4000, false));
});
