---
name: oro-k6-testing
description: "Use when setting up or running k6 performance tests for Oro Commerce 6.1 — load testing, storefront benchmarking, checkout performance, cache warm-up, or writing k6 scripts for Oro. Also on sight of warmingUpTheApp.js, storefrontTests.js, checkoutTest.js, or metrics like load_product_listing_page_logged_in_user. Triggers on 'k6', 'load test', 'performance test', 'THRESHOLD_95', 'VU', 'grafana k6', 'warm up'."
---

# Oro Commerce k6 Performance Testing

Oro ships a stock k6 harness (`oro/performance-tests`) exercising the storefront from outside: browsing, login, listing/search/detail, shopping list, checkout. Plain JavaScript, k6 as a single Go binary or the `grafana/k6` image, with named custom metrics you can gate individually.

## The Three-Script Pipeline

`performance/scripts/`, in this fixed order against a cleared cache — step 1 primes what steps 2 and 3 measure, so out of order inflates the numbers.

1. **`warmingUpTheApp.js`** — 1 VU / 60s / `THRESHOLD_95=3000`. Primes OPcache, Doctrine metadata, layout cache, search index.
2. **`storefrontTests.js`** — browse flow (home, listing, search, detail, login). Typical 1 VU / 600s / `THRESHOLD_95=850`.
3. **`checkoutTest.js`** — authenticated checkout from a shopping list. Needs `SL_ID`, `SHIPPING_METHOD`, `PAYMENT_METHOD`.

Do not reuse the load-run threshold (850 ms) for the warm-up — first-touch is always slower and the run will fail on cold caches.

## Environment Variables

All via `-e KEY=VALUE`. Every script takes `BASE_URL`, `USERNAME`, `PASSWORD`, `VU`, `DURATION` and `THRESHOLD_95` (`3000` warm-up, `850` load); `checkoutTest.js` adds `SL_ID`, `SHIPPING_METHOD` (`fixed_product_5`) and `PAYMENT_METHOD` (`payment_term_1`). Values and defaults: `references/v6.1.md`.

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

`--network host` lets k6 reach `localhost` and compose services; `-u $(id -u):$(id -g)` keeps `summary.html` from landing root-owned. Every flag: `references/docker-invocation.md`.

## Custom Oro Metrics

The scripts emit **14 named metrics** beyond the k6 defaults, so `options.thresholds` can gate each one rather than a single global p95. List and examples: `references/custom-metrics.md`.

## Reports

`handleSummary()` writes `summary.html` via `k6-reporter` — per-group timings, VU curves, threshold badges — alongside the stdout summary.

## Key Pitfalls

1. **Load testing without warming up** — cold caches inflate mean and p95 for everything. `warmingUpTheApp.js` at `THRESHOLD_95=3000` first, always.
2. **Docker without `-u $(id -u):$(id -g)`** — `summary.html` lands root-owned in the mounted volume; later runs cannot overwrite it and you cannot delete it without `sudo`.
3. **A single global `THRESHOLD_95`** — a slow listing hides behind a fast login in the aggregate. Threshold the named metrics.
4. **Default `--network bridge` against localhost** — the container cannot reach host compose services. Use `--network host`, or attach it to the compose network.

## See Also

In `references/`: `custom-metrics.md` (the 14 named metrics) · `docker-invocation.md` (flag by flag, network modes, ownership) · `default-metrics.md` (the 9 built-ins and derived KPIs) · `install.md` · `v6.1.md` · `v7.0.md`

- [Oro k6 docs](https://doc.oroinc.com/backend/automated-tests/k6-performance-tests/)
- [k6 documentation](https://k6.io/docs/)
