# Custom Oro k6 Metrics

Oro's stock k6 scripts emit 14 named metrics in addition to k6's built-ins. Each is a `Trend` metric (duration in ms) unless noted. Set per-metric thresholds in `options.thresholds` instead of relying on a single global p95 — a slow product listing will otherwise hide behind fast login requests in the aggregate.

## The 14 Metrics

| # | Metric Name | What It Measures |
|---|---|---|
| 1 | `authentication_post_request` | Duration of the `POST /customer/user/login` credential submit |
| 2 | `check_failure_rate` | Percentage of failed k6 `check()` assertions (Rate metric, 0.0–1.0) |
| 3 | `create_sl_request_post_request` | Duration of the shopping list create POST request |
| 4 | `create_sl_widget` | Duration of the shopping list creation widget render |
| 5 | `load_about_page_cms_guest_user` | About page (CMS) load time for an unauthenticated visitor |
| 6 | `load_home_page_guest_user` | Home page load time for an unauthenticated visitor |
| 7 | `load_home_page_logged_in_user` | Home page load time for an authenticated customer user |
| 8 | `load_login_page` | Login form page load time |
| 9 | `load_product_detail_page_guest_user` | Product detail (PDP) load time for a guest |
| 10 | `load_product_detail_page_logged_in_user` | Product detail (PDP) load time for a logged-in user |
| 11 | `load_product_listing_page_guest_user` | Category/listing page load for a guest |
| 12 | `load_product_listing_page_logged_in_user` | Category/listing page load for a logged-in user |
| 13 | `load_product_search_page_guest_user` | Search results page for a guest |
| 14 | `load_product_search_page_logged_in_user` | Search results page for a logged-in user |

Guest vs. logged-in pairs exist because authenticated browsing triggers price-list resolution, customer-scope ACL, and cart/shopping-list side panels — those are measurably heavier than anonymous requests and deserve separate thresholds.

## Per-Metric Thresholds

Instead of one global `http_req_duration` threshold, gate each named metric. Example `options` block inside a script:

```javascript
export const options = {
  vus: Number(__ENV.VU),
  duration: __ENV.DURATION,
  thresholds: {
    // Guest pages — primed cache, should be fast
    'load_home_page_guest_user':           ['p(95)<600'],
    'load_product_listing_page_guest_user':['p(95)<850'],
    'load_product_detail_page_guest_user': ['p(95)<900'],
    'load_product_search_page_guest_user': ['p(95)<1200'],

    // Logged-in pages — heavier, price-list + ACL
    'load_home_page_logged_in_user':             ['p(95)<900'],
    'load_product_listing_page_logged_in_user':  ['p(95)<1200'],
    'load_product_detail_page_logged_in_user':   ['p(95)<1400'],
    'load_product_search_page_logged_in_user':   ['p(95)<1600'],

    // Auth + shopping list creation
    'authentication_post_request':    ['p(95)<1000'],
    'create_sl_request_post_request': ['p(95)<1200'],
    'create_sl_widget':               ['p(95)<1500'],

    // Failure rate gate
    'check_failure_rate': ['rate<0.01'],
  },
};
```

## Warm-Up Overrides

During `warmingUpTheApp.js` the same metric names are emitted, but the cold-cache first-touch is 3–4× slower. Either set `THRESHOLD_95=3000` as a single global gate (current Oro default) or multiply the per-metric thresholds above by ~3 in the warm-up script. Do not run the warm-up with load-run thresholds — it will fail on legitimate cold-start cost.

## Reading Metric Values Out

Every run's stdout summary lists each named metric with `avg`, `min`, `med`, `max`, `p(90)`, `p(95)`. The HTML report (`summary.html`) renders the same table plus sparkline trends. For CI gating, use the JSON output (`--summary-export=summary.json`) and parse `metrics.<name>.values.p(95)`.
