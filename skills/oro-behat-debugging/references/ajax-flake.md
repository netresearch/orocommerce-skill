# AJAX Flakiness Root-Causing

"Flaky test" is almost never a test framework problem. It's a race condition the test is silent about. Treat every intermittent failure as a bug to root-cause, not noise to retry around.

## The Four Real Causes

### 1. `waitForAjax` doesn't see the request

Oro's `waitForAjax` polls jQuery's `$.active` counter. Only XHR requests made through `jQuery.ajax()` (and internally `$.get`/`$.post`) increment it. Everything else is invisible to the helper:

- `fetch(...)` — native Fetch API, used in any modernized storefront code.
- Raw `new XMLHttpRequest()` — rare but happens in third-party integrations.
- Web Workers, Service Worker fetches.
- `EventSource` / SSE streams.
- `navigator.sendBeacon()`.

If the storefront uses `fetch()` anywhere in the flow, `waitForAjax` returns immediately while the actual request is still in flight. The next step runs against a stale DOM.

**Fix:** wait for an observable DOM state that only appears after the real response has been processed. Never wait for the AJAX queue.

### 2. Detached element reference

You grab an element reference, then something re-renders the container, then you click. The reference points into a DOM subtree that's been replaced — Selenium raises `StaleElementReferenceError` intermittently depending on timing.

```php
// Fragile: find, act on another element that re-renders, then click.
$button = $this->find('css', '.add-to-cart');
$this->setQuantity(5);          // triggers re-render
$button->click();                // may be stale
```

**Fix:** re-query immediately before the action. Don't cache element references across any DOM-mutating call.

### 3. Backend state hasn't caught up

The HTTP request completed, but the business state the test asserts on hasn't finalized yet — typical when async message consumers handle the real work:

- "Order placed" returns 200 as soon as the order enters `pending`, but the test asserts on `in_progress` which a consumer sets.
- "Inventory updated" responds on write to the event queue, not on the read model being updated.
- "Email sent" is queued; the test looks at the spool seconds later.

`waitForAjax` is useless here — the HTTP round-trip already finished. The test needs to wait for the business state, not the transport.

**Fix:** poll the database or the observable entity state, not the network layer. Oro's `PageObjectContext::spin()` is the right primitive.

### 4. Silent JS throw inside a row/view render — `.loader-mask.shown` persists

`waitForAjax` also polls for `.loader-mask.shown`, `.lazy-loading`, `.loading-bar.show`, and mediator `isInAction`. Any of these sticking non-false makes it wait out the full timeout.

When a Backbone row view or Underscore template throws inside its render callback, Oro's datagrid never clears the loader mask — the DOM fragment covering the grid stays `.shown`, `waitForAjax` polls it for the full 120 s and Behat reports "Wait for ajax > 120 seconds" with no hint that anything was wrong server-side.

Classic trigger a preserved Underscore template uses bare `<%= varName %>` instead of `<%= obj.varName %>`. Underscore compiles to `with (obj || {}) { ... }`. As long as row models always carry `varName`, the `with` lookup works. The moment a new row-model variant (kit sub-row, grouped line item, variant child) legitimately lacks that field, render throws `ReferenceError: varName is not defined`, silently, inside the view — no PHP trace, no server error, nothing in `var/log/<env>.log`.

**Diagnostic recipe:**

1. Confirm the hang isn't a real pending XHR: in a debug step, dump `window.jQuery.active` during the wait. Zero = no real AJAX in flight; the blocker is one of the other `waitForAjax` predicates.
2. Capture Chrome browser console for the session. Add to `behat.yml` under the session's chromeOptions:
   ```yaml
   extra_capabilities:
       'goog:loggingPrefs':
           browser: ALL
   ```
3. Add a one-shot `@AfterStep` hook that dumps `$this->getSession()->getDriver()->getWebDriverSession()->log(['type' => 'browser'])` to a JSON file when the hang triggers. The ReferenceError (or TypeError, or whatever silent throw) is in there with file + line pointing at the compiled template chunk.
4. Follow the file reference back to the source `.html` template or `.js` view — the chunk filename usually carries the bundle's public-path segment.
5. **Revert the `goog:loggingPrefs` capability and the probe hook before committing.** They are diagnostic-only; leaving `browser: ALL` in CI capabilities bloats every test run's log payload.

**Why `waitForAjax` never warns you:** the helper is doing exactly what it was written to do — waiting for observable DOM signals to clear. It can't distinguish "request still loading" from "renderer threw and never cleared the mask." Once you know the pattern, the cause is an easy 5-minute fix (prefix bare refs with `obj.` in the throwing template). Getting there the first time costs hours.

## The Principled Pattern: Spin on a Predicate

Replace "sleep a bit longer" with "wait until the thing we actually care about is true":

```php
$this->spin(function () {
    return $this->getSession()->getPage()->find('css', '.product-added-notice') !== null;
}, 10); // 10-second ceiling
```

Or for backend state:

```php
$this->spin(function () {
    $order = $this->em->getRepository(Order::class)->find($orderId);
    $this->em->refresh($order);
    return $order->getStatus() === OrderStatus::IN_PROGRESS;
}, 30);
```

Key properties of a good predicate:

- **Observable** — something the test can check cheaply without side effects.
- **Causal** — it only becomes true after the thing you're waiting for has happened.
- **Idempotent** — polling it twice in a row gives the same answer until state changes.

## Diagnostic Checklist for a Flaky Scenario

1. Run it 10 times in a row (`for i in {1..10}; do ...; done`). Reproducible at > 10% failure rate? Race condition. Reproducible at < 1%? Still a race, but environmental.
2. Add `And I take screenshot` immediately before the failing step to capture the DOM state just before it blows up.
3. Check `var/log/<env>.log` for the exact moment of failure — async consumer errors surface there, not in the Behat output.
4. Is any storefront JS using `fetch()` instead of jQuery? `grep -r "fetch(" src/*/Resources/public/`. If yes, `waitForAjax` is lying to you.
5. Replace the last `waitForAjax` before the failure with a `spin` on an observable DOM or DB predicate.
