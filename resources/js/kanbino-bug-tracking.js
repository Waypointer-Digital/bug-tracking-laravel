/**
 * Kanbino Bug Tracking - JavaScript Error Capture
 *
 * Captures unhandled errors, promise rejections, and console errors.
 * Batches them and sends to the Kanbino ingestion API.
 */
(function () {
    'use strict';

    const config = window.__KANBINO_BT_CONFIG;
    if (!config || !config.dsn || !config.url) return;

    const queue = [];
    const breadcrumbs = [];
    const MAX_BREADCRUMBS = 30;
    let flushTimer = null;

    // Session ID for correlating events
    const sessionId = 'sess_' + Math.random().toString(36).substr(2, 16);

    function addBreadcrumb(type, category, message, data) {
        breadcrumbs.push({
            type,
            category,
            message: String(message).substring(0, 500),
            data,
            timestamp: new Date().toISOString(),
        });
        if (breadcrumbs.length > MAX_BREADCRUMBS) {
            breadcrumbs.shift();
        }
    }

    function normalizeStack(error) {
        if (!error || !error.stack) return [];
        const lines = error.stack.split('\n').slice(1);
        return lines.map(line => {
            const match = line.match(/at\s+(.+?)\s+\((.+?):(\d+):(\d+)\)/) ||
                          line.match(/at\s+(.+?):(\d+):(\d+)/) ||
                          line.match(/@(.+?):(\d+):(\d+)/);
            if (!match) return { file: line.trim(), line: 0, function: null, column: 0 };

            if (match.length === 5) {
                return { file: match[2], line: parseInt(match[3]), function: match[1], column: parseInt(match[4]) };
            }
            return { file: match[1], line: parseInt(match[2]), function: null, column: parseInt(match[3]) };
        }).filter(f => f.file);
    }

    function enqueue(payload) {
        queue.push(payload);
        scheduleFlush();
    }

    function scheduleFlush() {
        if (flushTimer) return;
        flushTimer = setTimeout(flush, config.batchInterval || 5000);
    }

    function flush() {
        flushTimer = null;
        if (queue.length === 0) return;

        const events = queue.splice(0, 25);
        const url = config.url + (events.length === 1 ? '/store' : '/batch');
        const body = events.length === 1 ? events[0] : { events };

        try {
            if (navigator.sendBeacon) {
                navigator.sendBeacon(url, JSON.stringify(body));
            } else {
                fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-BT-Key': config.dsn },
                    body: JSON.stringify(body),
                    keepalive: true,
                }).catch(() => {});
            }
        } catch (e) {
            // Silently fail
        }
    }

    function captureError(message, source, lineno, colno, error) {
        const stacktrace = error ? normalizeStack(error) : [
            { file: source || '', line: lineno || 0, function: null, column: colno || 0 }
        ];

        enqueue({
            title: String(message).substring(0, 1000),
            message: error ? error.message : String(message),
            type: error ? error.name : 'Error',
            level: 'error',
            platform: 'javascript',
            environment: config.environment || 'production',
            release: config.release || null,
            stacktrace,
            breadcrumbs: breadcrumbs.slice(),
            request_data: {
                url: window.location.href,
                method: 'GET',
                headers: { 'User-Agent': navigator.userAgent },
            },
            user_data: window.__KANBINO_BT_USER || null,
            replay_session_id: sessionId,
        });
    }

    // Global error handler
    window.onerror = function (message, source, lineno, colno, error) {
        captureError(message, source, lineno, colno, error);
    };

    // Unhandled promise rejections
    window.addEventListener('unhandledrejection', function (event) {
        const error = event.reason instanceof Error ? event.reason : null;
        const message = error ? error.message : String(event.reason);
        captureError('Unhandled Promise Rejection: ' + message, '', 0, 0, error);
    });

    // Console error capture
    if (config.captureConsole) {
        const originalError = console.error;
        const originalWarn = console.warn;

        console.error = function (...args) {
            addBreadcrumb('log', 'console.error', args.map(String).join(' '));
            return originalError.apply(console, args);
        };

        console.warn = function (...args) {
            addBreadcrumb('log', 'console.warn', args.map(String).join(' '));
            return originalWarn.apply(console, args);
        };
    }

    // XHR breadcrumbs
    const originalOpen = XMLHttpRequest.prototype.open;
    const originalSend = XMLHttpRequest.prototype.send;

    XMLHttpRequest.prototype.open = function (method, url) {
        this._kanbinoBt = { method, url: String(url) };
        return originalOpen.apply(this, arguments);
    };

    XMLHttpRequest.prototype.send = function () {
        const info = this._kanbinoBt;
        if (info && !info.url.includes('/api/bug-tracking/')) {
            const startTime = Date.now();
            this.addEventListener('loadend', function () {
                addBreadcrumb('http', 'xhr', `${info.method} ${info.url}`, {
                    method: info.method,
                    url: info.url,
                    status: this.status,
                    duration: Date.now() - startTime,
                });
            });
        }
        return originalSend.apply(this, arguments);
    };

    // Fetch breadcrumbs
    const originalFetch = window.fetch;
    if (originalFetch) {
        window.fetch = function (input, init) {
            const url = typeof input === 'string' ? input : input.url;
            const method = init?.method || 'GET';

            if (url.includes('/api/bug-tracking/')) {
                return originalFetch.apply(this, arguments);
            }

            const startTime = Date.now();
            return originalFetch.apply(this, arguments).then(function (response) {
                addBreadcrumb('http', 'fetch', `${method} ${url}`, {
                    method,
                    url,
                    status: response.status,
                    duration: Date.now() - startTime,
                });
                return response;
            }).catch(function (error) {
                addBreadcrumb('http', 'fetch', `${method} ${url} (failed)`, {
                    method,
                    url,
                    error: error.message,
                    duration: Date.now() - startTime,
                });
                throw error;
            });
        };
    }

    // Click breadcrumbs
    document.addEventListener('click', function (event) {
        const target = event.target;
        const tag = target.tagName?.toLowerCase();
        const text = (target.textContent || '').trim().substring(0, 50);
        const selector = tag + (target.id ? '#' + target.id : '') + (target.className ? '.' + String(target.className).split(' ')[0] : '');
        addBreadcrumb('user', 'ui.click', `${selector} "${text}"`);
    }, { capture: true, passive: true });

    // Navigation breadcrumbs
    window.addEventListener('popstate', function () {
        addBreadcrumb('navigation', 'navigation', window.location.href);
    });

    // Flush on page unload
    window.addEventListener('beforeunload', flush);
    window.addEventListener('pagehide', flush);

    // Expose for manual capture
    window.KanbinoCapture = captureError;
    window.KanbinoBreadcrumb = addBreadcrumb;
    window.KanbinoSetUser = function (user) {
        window.__KANBINO_BT_USER = user;
    };
})();
