---
name: oro-k6-testing
description: "Use when setting up or running k6 performance tests for Oro Commerce 6.1 — load testing, storefront benchmarking, checkout flow performance, cache warm-up, or writing/extending k6 JavaScript test scripts for Oro applications. Also use when you see warmingUpTheApp.js, storefrontTests.js, checkoutTest.js, or Oro performance metrics like load_product_listing_page_logged_in_user. Relevant when the user mentions 'k6', 'load test', 'performance test', 'THRESHOLD_95', 'virtual users', 'VU', 'storefront benchmark', 'checkout performance', 'grafana k6', 'warm up', 'oro performance', or any k6-against-Oro task."
---

# Oro Commerce k6 Performance Testing

## Overview

Oro ships a stock k6 test harness (from `oro/performance-tests`) that exercises the storefront from the outside — unauthenticated browsing, login, product listing/search/detail, shopping list creation, and checkout. Scripts are plain JavaScript, k6 is a single Go binary (or `grafana/k6` Docker image), and every run emits named custom metrics you can gate with per-metric thresholds.

## The Three-Script Pipeline

Scripts live in `performance/scripts/` and run in this fixed order against a fresh or cleared cache. The order matters because step 1 primes caches that steps 2 and 3 measure — running them out of order inflates the numbers.

1. **`warmingUpTheApp.js`** — 1 VU for 60s, `THRESHOLD_95=3000`. Primes OPcache, Doctrine metadata, layout cache, and search index warm-up paths. Skipping this makes the load runs look slower than reality.
2. **`storefrontTests.js`** — full browse flow (home, listing, search, product detail, login). Typical 1 VU / 600s / `THRESHOLD_95=850`.
3. **`checkoutTest.js`** — authenticated checkout from an existing shopping list. Requires `SL_ID`, `SHIPPING_METHOD`, `PAYMENT_METHOD`.

Do not reuse the load-run threshold (850 ms) for the warm-up — first-touch is always slower and the run will fail on cold caches.

## Environment Variables

All passed with `-e KEY=VALUE`:

| Var | Scripts | Example |
|-----|---------|---------|
| `BASE_URL` | all | `https://oro.docker.local` |
| `USERNAME` | all | `AmandaRCole@example.org` |
| `PASSWORD` | all | `AmandaRCole@example.org` |
| `VU` | all | `1` |
| `DURATION` | all | `60s`, `600s`, `10m` |
| `THRESHOLD_95` | all | `3000` (warm-up), `850` (load) |
| `SL_ID` | checkoutTest | `2` |
| `SHIPPING_METHOD` | checkoutTest | `fixed_product_5` |
| `PAYMENT_METHOD` | checkoutTest | `payment_term_1` |

## Hero Run Command (Docker)

```bash
docker run --rm --network host -u "$(id -u):$(id -g)" \
  -v "${PWD}/performance:/home/k6/performance" -w /home/k6/performance \
  grafana/k6:latest run \
  -e BASE_URL="https://oro.docker.local" \
  -e USERNAME="AmandaRCole@example.org" \
  -e PASSWORD="AmandaRCole@example.org" \
  -e VU=1 -e DURATION=60s -e THRESHOLD_95=3000 \
  scripts/warmingUpTheApp.js
```

`--network host` lets k6 reach `localhost` and compose services; `-u $(id -u):$(id -g)` keeps `summary.html` owned by your user instead of root. See `references/docker-invocation.md` for every flag.

## Custom Oro Metrics

Oro's scripts emit **14 named metrics** on top of the k6 defaults — `load_product_listing_page_logged_in_user`, `create_sl_request_post_request`, `authentication_post_request`, etc. You can set per-metric thresholds in `options.thresholds` instead of a single global 95th percentile. Full list and threshold examples in `references/custom-metrics.md`.

## Reports

`handleSummary()` in the stock scripts writes `summary.html` via `k6-reporter`. Open it locally — it contains per-group timing breakdowns, VU curves, and threshold pass/fail badges. A stdout text summary is also emitted.

## Key Pitfalls

1. **Running load tests without warming up first** — cold-cache results inflate mean/p95 for everything. Always run `warmingUpTheApp.js` with `THRESHOLD_95=3000` before any load run.
2. **Docker run without `-u $(id -u):$(id -g)`** — `summary.html` lands root-owned inside the mounted volume; subsequent runs fail to overwrite it and your user can't delete it without `sudo`.
3. **Single global `THRESHOLD_95`** — a slow product listing hides behind a fast login page in the aggregate. Target specific named metrics (e.g. `load_product_listing_page_logged_in_user`) in `options.thresholds`.
4. **Default `--network bridge` against localhost** — the container can't reach host compose services. Use `--network host` or attach the container to the compose network explicitly.

## See Also

- `references/custom-metrics.md` — 14 named Oro metrics, descriptions, per-metric threshold examples
- `references/docker-invocation.md` — full `grafana/k6` Docker run, flag-by-flag, network modes, file ownership
- `references/default-metrics.md` — the 9 built-in k6 metrics and derived KPIs (avg/peak RT, error rate, CRPS, concurrent users)
- `references/install.md` — Debian/Ubuntu, macOS, Windows, Docker install
- `references/v6.1.md` — v6.1 script layout, env var reference, warm-up rationale
- `references/v7.0.md` — 7.1-dev notes (placeholder)
- [Oro k6 docs](https://doc.oroinc.com/backend/automated-tests/k6-performance-tests/)
- [k6 documentation](https://k6.io/docs/)
