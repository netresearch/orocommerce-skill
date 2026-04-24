# Default k6 Metrics and KPIs

k6 emits nine built-in metrics automatically for every HTTP request, in addition to any custom metrics the script defines. These are always present — set global thresholds on them in `options.thresholds`.

## The 9 Built-in HTTP Metrics

| Metric | Type | What It Measures |
|---|---|---|
| `http_req_duration` | Trend | Total request duration: `http_req_sending + http_req_waiting + http_req_receiving`. The headline timing number. Excludes DNS, TCP, and TLS. |
| `http_req_waiting` | Trend | Time-to-first-byte (TTFB). Clock starts when the request is fully sent, stops on the first response byte. This is server-side processing time. |
| `http_req_connecting` | Trend | TCP handshake duration. Only non-zero on new connections (not reused from keep-alive pool). |
| `http_req_tls_handshaking` | Trend | TLS handshake duration. Zero on plain HTTP and on reused TLS connections. |
| `http_req_sending` | Trend | Time spent writing the request (headers + body) to the socket. Grows with large POST payloads. |
| `http_req_receiving` | Trend | Time spent reading the response body off the socket after TTFB. Grows with large responses on slow networks. |
| `http_req_blocked` | Trend | Time blocked before the request could start — usually DNS resolution and connection pool queuing. |
| `iteration_duration` | Trend | Wall clock for a single complete iteration of the default/exported function (one VU loop). |
| `group_duration` | Trend | Wall clock spent inside a `group()` block, measured per group tag. Lets you attribute timings to named flows. |

`http_req_duration` is the primary gate. Split it by `group_duration` when you need per-flow attribution without defining custom metrics.

## Derived KPIs

These aren't separate metrics but are computed from the built-ins and surfaced in Oro's reports and the k6-reporter HTML:

| KPI | How It's Computed | Why It Matters |
|---|---|---|
| **Average Response Time** | `avg(http_req_duration)` | First-glance health. Distorted by outliers — always pair with p95. |
| **Peak Response Time** | `max(http_req_duration)` | Worst single request in the run. One bad PHP-FPM stall shows up here. |
| **Error Rate** | `http_req_failed` rate, or `check_failure_rate` custom metric | Percentage of failed requests or failed assertions. Gate at < 1%. |
| **Throughput (CRPS)** | `http_reqs` count / duration | Completed requests per second. Compares capacity across runs at equal VU. |
| **Concurrent Users** | `vus` gauge | Current VUs during the run. `vus_max` is peak. |

## Setting Global Thresholds

```javascript
export const options = {
  thresholds: {
    http_req_duration:    ['p(95)<850', 'p(99)<2000'],
    http_req_failed:      ['rate<0.01'],
    iteration_duration:   ['p(95)<5000'],
  },
};
```

A threshold failure marks the run as failed (exit code 99 in k6 >= 0.50, or 1 in older versions) — useful for CI gating.
