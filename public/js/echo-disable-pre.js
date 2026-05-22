/**
 * До app.js: блокирует реальный Pusher/Echo на local (иначе зависание на wss://localhost).
 */
(function () {
  if (window.__DISABLE_LARAVEL_ECHO__ !== true) {
    return;
  }

  const noop = function noop() {
    return { listen: function () { return noop; } };
  };

  const echoStub = {
    private: noop,
    channel: noop,
    leave: function () {},
    disconnect: function () {},
  };

  const pusherStub = function () {
    const ch = { bind: function () {}, unbind: function () {} };
    return {
      subscribe: function () { return ch; },
      unsubscribe: function () {},
      disconnect: function () {},
      connection: ch,
      bind_global: function () {},
      unbind_global: function () {},
    };
  };

  Object.defineProperty(window, 'Pusher', {
    configurable: true,
    enumerable: true,
    get: function () { return pusherStub; },
    set: function () { /* ignore pusher-js */ },
  });

  Object.defineProperty(window, 'Echo', {
    configurable: true,
    enumerable: true,
    get: function () { return echoStub; },
    set: function () { /* ignore laravel-echo */ },
  });
})();
