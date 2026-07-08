(function (window) {
    'use strict'

    var startedAt = (window.performance && performance.now) ? performance.now() : Date.now()
    var PREFIX = '[Relevance]'

    function isEnabled() {
        try {
            if (localStorage.getItem('relevance_debug') === '0') {
                return false
            }
            if (localStorage.getItem('relevance_debug') === '1') {
                return true
            }
        } catch (e) {}

        if (/\brelevance_debug=1\b/.test(window.location.search)) {
            return true
        }

        return /localhost|127\.0\.0\.1/.test(window.location.hostname)
    }

    function elapsed() {
        var now = (window.performance && performance.now) ? performance.now() : Date.now()
        return ((now - startedAt) / 1000).toFixed(2) + 's'
    }

    function formatArgs(data) {
        if (data == null) {
            return ''
        }
        try {
            return ' ' + JSON.stringify(data)
        } catch (e) {
            return ' [unserializable]'
        }
    }

    function write(level, scope, message, data) {
        if (!isEnabled()) {
            return
        }
        var line = PREFIX + ' +' + elapsed() + ' [' + scope + '] ' + message + formatArgs(data)
        if (level === 'error') {
            console.error(line, data || '')
        } else if (level === 'warn') {
            console.warn(line)
        } else {
            console.log(line)
        }
    }

    function status(message) {
        if (message) {
            $('#preloaderStatus').text(message)
            $('#tablesPreloaderStatus').text(message)
        }
    }

    function clearStatus() {
        $('#tablesPreloaderStatus').text('')
    }

    window.relevancePageLog = {
        enabled: isEnabled,
        log: function (scope, message, data) {
            write('log', scope, message, data)
        },
        warn: function (scope, message, data) {
            write('warn', scope, message, data)
        },
        error: function (scope, message, data) {
            write('error', scope, message, data)
        },
        status: status,
        clearStatus: clearStatus,
        time: function (scope, label) {
            var t0 = (window.performance && performance.now) ? performance.now() : Date.now()
            write('log', scope, label + ' start')
            return {
                end: function (message, data) {
                    var t1 = (window.performance && performance.now) ? performance.now() : Date.now()
                    var payload = data || {}
                    payload.ms = Math.round(t1 - t0)
                    write('log', scope, label + ' ' + (message || 'done'), payload)
                },
            }
        },
    }

    if (isEnabled()) {
        write('log', 'boot', 'debug logging enabled', {
            url: window.location.pathname,
            hint: 'localStorage.relevance_debug=1|0',
        })

        window.addEventListener('error', function (event) {
            write('error', 'window', event.message || 'error', {
                file: event.filename,
                line: event.lineno,
            })
        })

        window.addEventListener('unhandledrejection', function (event) {
            write('error', 'promise', String(event.reason))
        })
    }
})(window)
