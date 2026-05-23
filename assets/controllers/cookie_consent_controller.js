import { Controller } from '@hotwired/stimulus';

/**
 * Cookie consent banner controller for GDPR compliance.
 *
 * Manages cookie consent display and storage.
 * Essential cookies (session, CSRF) are always set.
 * User consent is stored in localStorage.
 *
 * Usage:
 * <div data-controller="cookie-consent" data-cookie-consent-consent-key-value="cookie_consent">
 *     <div data-cookie-consent-target="banner">Banner content</div>
 * </div>
 */
export default class extends Controller {
    static targets = ['banner'];
    static values = {
        consentKey: { type: String, default: 'cookie_consent' }
    };

    connect() {
        // Check if consent has already been given
        const consent = this.getConsent();
        if (!consent) {
            this.showBanner();
        }

        // Listen for global event to show banner (from external buttons)
        this.handleGlobalShow = this.showBanner.bind(this);
        window.addEventListener('cookie-consent:show', this.handleGlobalShow);
    }

    disconnect() {
        window.removeEventListener('cookie-consent:show', this.handleGlobalShow);
    }

    /**
     * Accept all cookies
     */
    acceptAll() {
        this.saveConsent({
            essential: true,
            functional: true,
            timestamp: new Date().toISOString()
        });
        this.hideBanner();
    }

    /**
     * Accept only essential cookies
     */
    acceptEssential() {
        this.saveConsent({
            essential: true,
            functional: false,
            timestamp: new Date().toISOString()
        });
        this.hideBanner();
    }

    /**
     * Show the cookie banner (for preference update)
     */
    showBanner() {
        if (this.hasBannerTarget) {
            this.bannerTarget.classList.remove('hidden');
            this.bannerTarget.setAttribute('aria-hidden', 'false');
        }
    }

    /**
     * Hide the cookie banner
     */
    hideBanner() {
        if (this.hasBannerTarget) {
            this.bannerTarget.classList.add('hidden');
            this.bannerTarget.setAttribute('aria-hidden', 'true');
        }
    }

    /**
     * Get current consent from localStorage
     */
    getConsent() {
        try {
            const stored = localStorage.getItem(this.consentKeyValue);
            return stored ? JSON.parse(stored) : null;
        } catch (e) {
            return null;
        }
    }

    /**
     * Save consent to localStorage
     */
    saveConsent(consent) {
        try {
            localStorage.setItem(this.consentKeyValue, JSON.stringify(consent));
        } catch (e) {
            console.warn('Could not save cookie consent:', e);
        }
    }

    /**
     * Check if functional cookies are allowed
     */
    hasFunctionalConsent() {
        const consent = this.getConsent();
        return consent && consent.functional === true;
    }
}
