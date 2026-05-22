/**
 * Локально без laravel-websockets: заглушка после app.js (если в консоли ещё есть wss-ошибки).
 */
(function () {
  if (window.__DISABLE_LARAVEL_ECHO__ !== true) {
    return;
  }
  const noop = () => ({ listen: () => noop });
  window.Echo = {
    private: noop,
    channel: noop,
    leave: () => {},
    disconnect: () => {},
  };
})();
