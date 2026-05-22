#!/usr/bin/env node
/**
 * Прокси :3002 → php artisan serve (несколько воркеров).
 * Короткий таймаут, failover без повторного pipe тела запроса.
 */
import http from 'node:http';

const LISTEN = Number(process.env.CABINET_PROXY_PORT || 3002);
const UPSTREAM_MS = Number(process.env.CABINET_PROXY_TIMEOUT_MS || 20_000);
const BACKENDS = (process.env.CABINET_WORKER_PORTS || '13002,13003,13004')
  .split(',')
  .map((p) => Number(p.trim()))
  .filter(Boolean);

if (BACKENDS.length === 0) {
  console.error('CABINET_WORKER_PORTS пуст');
  process.exit(1);
}

const unhealthyUntil = new Map();
let rr = 0;

function markUnhealthy(port) {
  unhealthyUntil.set(port, Date.now() + 45_000);
}

function healthyBackends() {
  const now = Date.now();
  const ok = BACKENDS.filter((p) => (unhealthyUntil.get(p) || 0) <= now);
  return ok.length ? ok : [...BACKENDS];
}

function pickBackend(exclude = new Set()) {
  const pool = healthyBackends().filter((p) => !exclude.has(p));
  if (!pool.length) return null;
  return pool[rr++ % pool.length];
}

function forward(clientReq, clientRes, body) {
  const tried = new Set();

  const attempt = () => {
    const port = pickBackend(tried);
    if (port === null) {
      if (!clientRes.headersSent) {
        clientRes.writeHead(502, { 'Content-Type': 'text/plain; charset=utf-8' });
      }
      clientRes.end(
        'Кабинет: все воркеры заняты или не отвечают.\n' +
          'Перезапуск: cd cabinet.datagon.ru && bash scripts/dev-parallel.sh stop && bash scripts/dev-parallel.sh\n',
      );
      return;
    }

    tried.add(port);
    const headers = { ...clientReq.headers };
    headers.host = `127.0.0.1:${port}`;

    const upstream = http.request(
      {
        hostname: '127.0.0.1',
        port,
        path: clientReq.url,
        method: clientReq.method,
        headers,
        timeout: UPSTREAM_MS,
      },
      (upstreamRes) => {
        clientRes.writeHead(upstreamRes.statusCode, upstreamRes.headers);
        upstreamRes.pipe(clientRes);
      },
    );

    upstream.on('timeout', () => {
      upstream.destroy();
      markUnhealthy(port);
      if (!clientRes.headersSent) attempt();
    });

    upstream.on('error', () => {
      markUnhealthy(port);
      if (!clientRes.headersSent) attempt();
    });

    if (body.length) upstream.write(body);
    upstream.end();
  };

  attempt();
}

const server = http.createServer((clientReq, clientRes) => {
  const chunks = [];
  clientReq.on('data', (c) => chunks.push(c));
  clientReq.on('end', () => forward(clientReq, clientRes, Buffer.concat(chunks)));
  clientReq.on('error', () => {
    if (!clientRes.headersSent) clientRes.writeHead(400);
    clientRes.end();
  });
});

server.listen(LISTEN, '127.0.0.1', () => {
  console.log(
    `cabinet proxy http://localhost:${LISTEN} → ${BACKENDS.join(', ')} (timeout ${UPSTREAM_MS}ms)`,
  );
});

function shutdown() {
  server.close(() => process.exit(0));
}
process.on('SIGINT', shutdown);
process.on('SIGTERM', shutdown);
