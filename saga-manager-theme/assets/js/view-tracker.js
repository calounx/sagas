/**
 * View Tracker - Privacy-first analytics
 *
 * @package Saga_Manager_Theme
 */

class SagaViewTracker {
    constructor() {
        this.startTime = Date.now();
        this.visitorId = this.getVisitorId();
        this.tracked = false;
        this.entityId = this.getEntityId();
        this.dntEnabled = this.isDNTEnabled();
        this.minEngagementTime = 5000; // 5 seconds
        this.maxDuration = 7200; // 2 hours

        if (this.entityId && !this.dntEnabled) {
            this.init();
        }
    }

    /**
     * Initialize tracking
     */
    init() {
        // Track view after engagement threshold
        setTimeout(() => this.trackView(), this.minEngagementTime);

        // Track duration on page unload
        window.addEventListener('beforeunload', () => this.trackDuration());

        // Track duration on visibility change (tab switch)
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'hidden') {
                this.trackDuration();
            }
        });

        // Heartbeat every 30 seconds for long sessions
        setInterval(() => this.trackHeartbeat(), 30000);
    }

    /**
     * Track page view
     */
    trackView() {
        if (this.tracked) {
            return;
        }

        const formData = new URLSearchParams({
            action: 'saga_track_view',
            nonce: sagaAnalytics.nonce,
            entity_id: this.entityId,
            visitor_id: this.visitorId,
        });

        fetch(sagaAnalytics.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: formData,
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.tracked = true;
                this.dispatchEvent('saga:view-tracked', { entityId: this.entityId });
            }
        })
        .catch(error => {
            console.debug('Saga Analytics: View tracking failed', error);
        });
    }

    /**
     * Track time spent on page
     */
    trackDuration() {
        if (!this.tracked) {
            return; // Don't track duration if view wasn't tracked
        }

        const duration = Math.floor((Date.now() - this.startTime) / 1000);

        // Ignore bounces and unrealistic durations
        if (duration < 5 || duration > this.maxDuration) {
            return;
        }

        const formData = new URLSearchParams({
            action: 'saga_track_duration',
            nonce: sagaAnalytics.nonce,
            entity_id: this.entityId,
            visitor_id: this.visitorId,
            duration: duration,
        });

        // Use sendBeacon for reliable tracking on page unload
        if (navigator.sendBeacon) {
            navigator.sendBeacon(sagaAnalytics.ajaxUrl, formData);
        } else {
            // Fallback for browsers without sendBeacon
            fetch(sagaAnalytics.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: formData,
                keepalive: true,
            }).catch(() => {
                // Silent fail on unload
            });
        }

        this.dispatchEvent('saga:duration-tracked', {
            entityId: this.entityId,
            duration: duration
        });
    }

    /**
     * Heartbeat for long sessions
     */
    trackHeartbeat() {
        const duration = Math.floor((Date.now() - this.startTime) / 1000);

        // Only send heartbeat for sessions > 1 minute
        if (duration < 60 || duration > this.maxDuration) {
            return;
        }

        this.trackDuration();
    }

    /**
     * Get or create visitor ID
     */
    getVisitorId() {
        const storageKey = 'saga_visitor_id';
        let id = localStorage.getItem(storageKey);

        if (!id) {
            id = this.generateVisitorId();
            try {
                localStorage.setItem(storageKey, id);
            } catch (e) {
                // Handle localStorage unavailable
                console.debug('Saga Analytics: localStorage unavailable');
            }
        }

        return id;
    }

    /**
     * Generate anonymous visitor ID
     */
    generateVisitorId() {
        // Use crypto.randomUUID if available
        if (typeof crypto !== 'undefined' && crypto.randomUUID) {
            return crypto.randomUUID();
        }

        // Fallback UUID v4 generator
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            const r = Math.random() * 16 | 0;
            const v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }

    /**
     * Get entity ID from page
     */
    getEntityId() {
        // Check if entity ID is provided in global object
        if (typeof sagaAnalytics !== 'undefined' && sagaAnalytics.entityId) {
            return parseInt(sagaAnalytics.entityId, 10);
        }

        // Fallback: check body data attribute
        const bodyEntityId = document.body.getAttribute('data-entity-id');
        if (bodyEntityId) {
            return parseInt(bodyEntityId, 10);
        }

        return null;
    }

    /**
     * Check if Do Not Track is enabled
     */
    isDNTEnabled() {
        return navigator.doNotTrack === '1' ||
               window.doNotTrack === '1' ||
               navigator.msDoNotTrack === '1';
    }

    /**
     * Dispatch custom event
     */
    dispatchEvent(eventName, detail) {
        const event = new CustomEvent(eventName, { detail });
        document.dispatchEvent(event);
    }

    /**
     * Public method to manually track an action
     */
    trackCustomAction(actionName, metadata = {}) {
        if (!this.tracked || this.dntEnabled) {
            return;
        }

        const formData = new URLSearchParams({
            action: 'saga_track_custom_action',
            nonce: sagaAnalytics.nonce,
            entity_id: this.entityId,
            visitor_id: this.visitorId,
            action_name: actionName,
            metadata: JSON.stringify(metadata),
        });

        fetch(sagaAnalytics.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: formData,
        }).catch(() => {
            // Silent fail
        });
    }
}

// Initialize tracker when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.sagaTracker = new SagaViewTracker();
    });
} else {
    window.sagaTracker = new SagaViewTracker();
}

// Expose tracker for manual tracking
window.SagaViewTracker = SagaViewTracker;
