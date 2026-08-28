<?php
header('Content-Type: application/javascript; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
require_once __DIR__ . '/config/conexao.php';
require_once __DIR__ . '/config/versao.php';
$b = defined('BASE') ? BASE : '';
?>
// Agro Life Service Worker
// CACHE_NAME acompanha APP_VERSAO — cada deploy novo já derruba o cache antigo
// sozinho, sem precisar bump manual de versão aqui.
const CACHE_NAME = 'agrolife-<?= APP_VERSAO ?>';

// Assets estáticos que não mudam entre requisições
const STATIC_ASSETS = [
    '<?= $b ?>/assets/css/paleta.css?v=<?= APP_VERSAO ?>',
    '<?= $b ?>/assets/css/estrutura.css?v=<?= APP_VERSAO ?>',
    '<?= $b ?>/assets/img/logo.png',
    '<?= $b ?>/assets/img/icone.ico',
];

// Pré-cache dos assets críticos na instalação
self.addEventListener('install', e => {
    e.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => cache.addAll(STATIC_ASSETS).catch(() => {}))
            .then(() => self.skipWaiting())
    );
});

// Remove caches de versões antigas na ativação
self.addEventListener('activate', e => e.waitUntil(
    caches.keys()
        .then(keys => Promise.all(
            keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k))
        ))
        .then(() => clients.claim())
));

self.addEventListener('fetch', e => {
    const req = e.request;
    const url = new URL(req.url);

    // Navegações (páginas PHP dinâmicas) → sempre do servidor
    if (req.mode === 'navigate') return;

    // Assets estáticos (CSS, JS, imagens, fontes) → cache-first
    const isStatic = /\.(css|js|png|ico|jpg|jpeg|gif|svg|webp|woff2?|ttf|eot)(\?.*)?$/.test(url.pathname);

    if (isStatic && url.origin === self.location.origin) {
        e.respondWith(
            caches.match(req).then(cached => {
                if (cached) return cached;
                return fetch(req).then(res => {
                    if (res.ok) {
                        const clone = res.clone();
                        caches.open(CACHE_NAME).then(c => c.put(req, clone));
                    }
                    return res;
                }).catch(() => new Response('', { status: 503 }));
            })
        );
        return;
    }

    // Demais requisições → rede com fallback silencioso
    e.respondWith(
        fetch(req).catch(() => new Response('', { status: 503, statusText: 'Offline' }))
    );
});

// Push: exibe notificação nativa
self.addEventListener('push', e => {
    let d = { title: '<?= addslashes(APP_NOME) ?>', body: 'Você tem uma novidade.', url: '<?= $b ?>/painel/index.php' };
    if (e.data) { try { d = Object.assign(d, e.data.json()); } catch (_) {} }
    e.waitUntil(
        self.registration.showNotification(d.title, {
            body:  d.body,
            icon:  '<?= $b ?>/assets/img/logo.png',
            badge: '<?= $b ?>/assets/img/logo.png',
            data:  { url: d.url },
            tag:   d.tag || undefined,
        })
    );
});

// Clique na notificação
self.addEventListener('notificationclick', e => {
    e.notification.close();
    const url = e.notification.data?.url || '<?= $b ?>/painel/index.php';
    e.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(lista => {
            for (const c of lista) {
                if ('focus' in c) { c.navigate(url); return c.focus(); }
            }
            if (clients.openWindow) return clients.openWindow(url);
        })
    );
});
