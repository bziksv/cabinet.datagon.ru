window._ = require('lodash');

/**
 * We'll load the axios HTTP library which allows us to easily issue requests
 * to our Laravel back-end. This library automatically handles sending the
 * CSRF token as a header based on the value of the "XSRF" token cookie.
 */

window.axios = require('axios');

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

/**
 * Password generator
 * @type {{GenerateOptions: GenerateOptions; generate(options?: GenerateOptions): string; generateMultiple(count: number, options?: GenerateOptions): string[]}}
 */
window.generator = require('generate-password');

window.cookies = require('js-cookie');

window.copy = require('copy-to-clipboard');

window.hash = require('hash.subscribe');

window.currencyFormatter = require('currency-formatter');

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allows your team to easily build robust real-time web applications.
 */

/**
 * Заглушка Echo — страницы с .listen() не падают без websocket-сервера.
 */
function echoStub() {
    const noop = () => ({ listen: () => noop });
    return { private: noop, channel: noop, leave: () => {}, disconnect: () => {} };
}

const echoEnabled = typeof window !== 'undefined'
    && !window.__DISABLE_LARAVEL_ECHO__
    && (window.__LARAVEL_ECHO_PORT__ || '').length > 0;

if (echoEnabled) {
    const Echo = require('laravel-echo');

    window.Pusher = require('pusher-js');

    const port = Number(window.__LARAVEL_ECHO_PORT__);
    const useTls = window.__LARAVEL_ECHO_TLS__ === true || window.__LARAVEL_ECHO_TLS__ === 'true';

    window.Echo = new Echo({
        broadcaster: 'pusher',
        key: window.__LARAVEL_ECHO_KEY__ || 'local',
        wsHost: window.location.hostname,
        wsPort: port,
        wssPort: port,
        forceTLS: useTls,
        disableStats: true,
        enabledTransports: useTls ? ['wss'] : ['ws'],
    });
} else {
    window.Echo = echoStub();
}

window.XLSX = require('xlsx-js-style');

function rgbaToHex(rgba) {
    const match = rgba.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*([\d.]+))?\)/);
    if (!match) return "FFFFFF";

    const r = parseInt(match[1]).toString(16).padStart(2, '0');
    const g = parseInt(match[2]).toString(16).padStart(2, '0');
    const b = parseInt(match[3]).toString(16).padStart(2, '0');

    return (r + g + b).toUpperCase();
}

function generateRgbaColor() {
    const red = Math.floor(Math.random() * 128) + 128;
    const green = Math.floor(Math.random() * 128) + 128;
    const blue = Math.floor(Math.random() * 128) + 128;

    return "rgb(" + red + ", " + green + ", " + blue + ")";
}

window.exportToExcel = function(data, filename = 'export.xlsx') {
    const worksheet = window.XLSX.utils.json_to_sheet(data);

    const cols = [];
    const columnHeaders = Object.keys(data[0] || {});

    columnHeaders.forEach(header => {
        const maxLength = data.reduce((max, row) => {
            const cellValue = row[header] ? String(row[header]) : '';
            return Math.max(max, cellValue.length, header.length);
        }, 0);

        cols.push({ wch: maxLength }); // +2 для отступов
    });

    worksheet['!cols'] = cols;

    // Раскраска заголовков
    const range = window.XLSX.utils.decode_range(worksheet['!ref']);

    const valueMap = new Map();
    for (let C = range.s.c; C <= range.e.c; ++C) {

        // Собираем уникальные значения в колонке (начиная со 2-й строки)
        for (let R = range.s.r + 1; R <= range.e.r; ++R) {
            const address = window.XLSX.utils.encode_cell({r: R, c: C});
            const cell = worksheet[address];

            if (!cell || !cell.v) continue;

            const value = String(cell.v);

            if (!valueMap.has(value)) {
                valueMap.set(value, generateRgbaColor());
            }
        }
    }

    // Применяем цвета только если есть дубликаты
    valueMap.forEach((color, value) => {
        const cells = [];

        for (let C = range.s.c; C <= range.e.c; ++C) {
            for (let R = range.s.r + 1; R <= range.e.r; ++R) {
                const address = window.XLSX.utils.encode_cell({ r: R, c: C });
                const cell = worksheet[address];
                if (cell && String(cell.v) === value) {
                    cells.push(address);
                }
            }
        }

        // Красим только если значение повторяется
        if (cells.length > 1) {
            cells.forEach(addr => {
                worksheet[addr].s = {
                    fill: { fgColor: { rgb: rgbaToHex(color) } }
                };
            });
        }
    });

    const workbook = window.XLSX.utils.book_new();
    window.XLSX.utils.book_append_sheet(workbook, worksheet, 'Sheet1');
    window.XLSX.writeFile(workbook, filename);
};


