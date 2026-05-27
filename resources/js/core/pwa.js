// resources/js/core/pwa.js
/**
 * Gestionnaire PWA : Enregistrement, détection réseau et installation personnalisée.
 * Supporte iOS Safari (instructions manuelles) et Android/Chrome (beforeinstallprompt).
 */

let deferredPrompt = null;

function isIOS() {
    return /iphone|ipad|ipod/i.test(navigator.userAgent) ||
           (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
}

function isInStandaloneMode() {
    return window.matchMedia('(display-mode: standalone)').matches ||
           window.navigator.standalone === true;
}

function wasBannerDismissed() {
    const dismissed = localStorage.getItem('pwa-banner-dismissed');
    if (!dismissed) return false;
    const dismissedTime = parseInt(dismissed, 10);
    const sevenDays = 7 * 24 * 60 * 60 * 1000;
    return (Date.now() - dismissedTime) < sevenDays;
}

export function initPWA() {
    // 1. Enregistrement du Service Worker
    if ('serviceWorker' in navigator) {
        const registerSW = () => {
            navigator.serviceWorker.register('/sw.js')
                .then((reg) => console.log('💚 [PWA] SW enregistré:', reg.scope))
                .catch((err) => console.error('❤️ [PWA] Échec SW:', err));
        };
        if (document.readyState === 'complete') registerSW();
        else window.addEventListener('load', registerSW);
    }

    // 2. Réseau
    window.addEventListener('online', updateNetworkStatus);
    window.addEventListener('offline', updateNetworkStatus);
    updateNetworkStatus();

    // 3. Installation
    if (isInStandaloneMode()) {
        console.log('✅ [PWA] Déjà en mode standalone.');
        return;
    }
    if (wasBannerDismissed()) {
        console.log('🔇 [PWA] Bannière masquée (fermée récemment).');
        return;
    }

    if (isIOS()) {
        console.log('🍎 [PWA] iOS détecté — instructions manuelles.');
        showIOSInstallBanner();
    } else {
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            showNativeInstallBanner();
        });
    }

    window.addEventListener('appinstalled', () => {
        hideInstallBanner();
        deferredPrompt = null;
    });
}

function updateNetworkStatus() {
    const isOnline = navigator.onLine;
    window.dispatchEvent(new CustomEvent('network:status', { detail: { isOnline } }));

    document.querySelectorAll('.live-indicator').forEach(el => {
        el.style.background = isOnline ? 'var(--success)' : 'var(--accent)';
        el.style.boxShadow = `0 0 10px ${isOnline ? 'var(--success)' : 'var(--accent)'}`;
    });
    document.querySelectorAll('.network-status-text').forEach(el => {
        el.textContent = isOnline ? 'Live Sync' : 'Offline Mode';
        el.style.color = isOnline ? 'var(--success)' : 'var(--accent)';
    });
}

/**
 * Bannière iOS — instructions visuelles étape par étape
 */
function showIOSInstallBanner() {
    const banner = document.getElementById('pwa-install-banner');
    if (!banner) return;

    // Icône "Partager" iOS (carré avec flèche vers le haut)
    const shareIcon = `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#007AFF" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12v8a2 2 0 002 2h12a2 2 0 002-2v-8"/><polyline points="16 6 12 2 8 6"/><line x1="12" y1="2" x2="12" y2="15"/></svg>`;

    // Icône "+" (ajouter à l'écran d'accueil)
    const plusIcon = `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="4"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>`;

    banner.innerHTML = `
        <div class="ios-install-guide">
            <div class="ios-install-header">
                <span style="font-size:1.8rem;">📲</span>
                <div>
                    <strong style="color:#fff;font-size:1.05rem;">Installer BATTLEPOOL</strong>
                    <p style="color:rgba(255,255,255,0.5);font-size:0.75rem;margin:2px 0 0 0;">Jouez en plein écran depuis votre écran d'accueil</p>
                </div>
                <button onclick="hidePWAInstall()" class="btn-pwa-close" style="margin-left:auto;font-size:1.3rem;background:none;border:none;color:rgba(255,255,255,0.4);cursor:pointer;">✕</button>
            </div>
            <div class="ios-install-steps">
                <div class="ios-step">
                    <div class="ios-step-number">1</div>
                    <div class="ios-step-icon">${shareIcon}</div>
                    <span>Appuyez sur <strong style="color:#007AFF;">Partager</strong> dans la barre Safari</span>
                </div>
                <div class="ios-step-arrow">→</div>
                <div class="ios-step">
                    <div class="ios-step-number">2</div>
                    <div class="ios-step-icon">${plusIcon}</div>
                    <span>Puis <strong style="color:#fff;">Sur l'écran d'accueil</strong></span>
                </div>
            </div>
        </div>
    `;

    banner.style.display = 'block';
    banner.classList.add('fade-in');
}

function showNativeInstallBanner() {
    const banner = document.getElementById('pwa-install-banner');
    if (!banner) return;
    banner.style.display = 'flex';
    banner.classList.add('fade-in');
}

function hideInstallBanner() {
    const banner = document.getElementById('pwa-install-banner');
    if (banner) banner.style.display = 'none';
    localStorage.setItem('pwa-banner-dismissed', Date.now().toString());
}

export async function installApp() {
    if (!deferredPrompt) return;
    deferredPrompt.prompt();
    const { outcome } = await deferredPrompt.userChoice;
    console.log(`👤 [PWA] Choix: ${outcome}`);
    deferredPrompt = null;
    hideInstallBanner();
}

window.installPWA = installApp;
window.hidePWAInstall = hideInstallBanner;
