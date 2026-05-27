# Breadth-First Diagnostic Steps

A behat scenario fails with a "thing missing / empty" symptom. Several plausible gates could be responsible. Don't hypothesise one at a time — write one diagnostic step that dumps every plausible culprit in a single run.

## Why

Each `make behat FEATURE=…` cycle costs 2–4 minutes (baseline restore + ES reindex + fixture load + scenario). Narrowing a multi-gate failure by re-running once per hypothesis compounds to 20–40 minutes of sequential wall time when a single dump would have returned the answer in one run.

Human asks: *"can you add more diagnose to see ALL possible culprits and not do 200 single runs?"* — the reaction to the anti-pattern.

## Pattern

Add a temporary `@Then I dump X for :arg on :arg` step to a Context. It:
1. Resolves the entities under investigation (product, website, cart, etc.).
2. Queries every related table via DBAL (rule + resolved, chain tables, scopes).
3. Optionally queries Elasticsearch per-website-index for the entity presence.
4. Optionally pulls system config values scoped to the entity.
5. Wraps each sub-query in `try/catch` so one missing table / wrong service name doesn't suppress the rest.
6. `throw new \RuntimeException("=== DUMP ===\n" . json_encode(...))` so the combined state lands in behat output directly.

After one run you have ground truth. Then think.

## Example: product-listing visibility dump

```php
/**
 * @Then I dump visibility for :sku on website :websiteName
 */
public function iDumpVisibilityFor(string $sku, string $websiteName): void
{
    $container = $this->getAppContainer();
    $doctrine = $container->get('doctrine');
    $product = $doctrine->getRepository(Product::class)->findOneBy(['sku' => $sku]);
    $website = $doctrine->getRepository(Website::class)->findOneBy(['name' => $websiteName]);

    $conn = $doctrine->getConnection();
    $pid = $product?->getId() ?? 0;
    $wid = $website?->getId() ?? 0;

    // Product core
    $productCore = $conn->fetchAssociative('SELECT id, sku, status, category_id, organization_id FROM oro_product WHERE id = ?', [$pid]);

    // All six product visibility tables
    $tables = [
        'oro_product_visibility' => "SELECT pv.id, pv.visibility, s.id AS scope_id, s.website_id, s.customergroup_id, s.customer_id
            FROM oro_product_visibility pv JOIN oro_scope s ON s.id = pv.scope_id WHERE pv.product_id = ?",
        'oro_prod_vsb_resolv' => 'SELECT * FROM oro_prod_vsb_resolv WHERE product_id = ?',
        'oro_cus_grp_prod_visibility' => "SELECT v.id, v.visibility, s.id AS scope_id, s.website_id, s.customergroup_id
            FROM oro_cus_grp_prod_visibility v JOIN oro_scope s ON s.id = v.scope_id WHERE v.product_id = ?",
        'oro_cus_grp_prod_vsb_resolv' => 'SELECT * FROM oro_cus_grp_prod_vsb_resolv WHERE product_id = ?',
        'oro_cus_product_visibility' => "SELECT v.id, v.visibility, s.id AS scope_id, s.website_id, s.customer_id
            FROM oro_cus_product_visibility v JOIN oro_scope s ON s.id = v.scope_id WHERE v.product_id = ?",
        'oro_cus_prod_vsb_resolv' => 'SELECT * FROM oro_cus_prod_vsb_resolv WHERE product_id = ?',
    ];
    $results = [];
    foreach ($tables as $name => $sql) {
        try {
            $results[$name] = $conn->fetchAllAssociative($sql, [$pid]);
        } catch (\Throwable $e) {
            $results[$name] = 'ERROR: ' . $e->getMessage();
        }
    }

    // ES hits per website-scoped index
    $es = [];
    try {
        $engine = $container->get('oro_website_search.eleastic_search.engine');
        $esClient = $engine->getClient();
        $indexNames = array_keys(($esClient->indices()->stats())['indices'] ?? []);
        $websiteIndexes = array_filter($indexNames, fn ($n) => str_contains($n, "_{$wid}_"));
        foreach ($websiteIndexes ?: $indexNames as $index) {
            try {
                $resp = $esClient->search([
                    'index' => $index,
                    'body' => ['query' => ['match_phrase' => ['sku' => $sku]], 'size' => 3],
                ]);
                $es[$index] = $resp['hits']['total']['value'] ?? 0;
            } catch (\Throwable $e) {
                $es[$index] = 'ERROR: ' . $e->getMessage();
            }
        }
    } catch (\Throwable $e) {
        $es = 'ES lookup failed: ' . $e->getMessage();
    }

    // System config relevant to the domain
    $cfg = [];
    try {
        $cm = $container->get('oro_config.website');
        foreach (['oro_visibility.product_visibility', 'oro_lab_cart.availability_for_guests'] as $k) {
            $cfg[$k] = $cm->get($k, false, false, $website);
        }
    } catch (\Throwable $e) {
        $cfg = 'config lookup failed: ' . $e->getMessage();
    }

    throw new \RuntimeException(
        "=== DUMP sku=$sku website=$websiteName (id=$wid) ===\n"
        . 'Product core: ' . json_encode($productCore) . "\n"
        . 'Visibility tables: ' . json_encode($results, JSON_PRETTY_PRINT) . "\n"
        . 'ES: ' . json_encode($es, JSON_PRETTY_PRINT) . "\n"
        . 'Config: ' . json_encode($cfg)
    );
}
```

## Checklist for writing a breadth-first dump

- [ ] Resolve all entities under investigation up-front (product, website, customer, etc.).
- [ ] List every related table — don't assume naming convention (Oro uses non-obvious abbreviated tables like `oro_prod_vsb_resolv`, `oro_ctgr_vsb_resolv`, `oro_cus_grp_prod_vsb_resolv`). Verify with `\dt` against the running DB before writing the step.
- [ ] Include scope rows for the website so FK `scope_id` values in other tables can be cross-referenced.
- [ ] Wrap each sub-query in `try/catch` — the step must dump everything it CAN find even when some queries fail.
- [ ] Include Elasticsearch hits per-index when the grid is ES-backed.
- [ ] Include relevant system-config keys.
- [ ] Use `json_encode(..., JSON_PRETTY_PRINT)` for nested sections.
- [ ] Throw as `RuntimeException` so it's unmistakable in behat output.
- [ ] Revert the diagnostic step and the Gherkin invocation before committing — it's debugging scaffolding, not permanent test coverage.

## Variants

- **Price resolution dump**: for a product+customer+website, dump `oro_price_list_to_product`, `oro_price_list_to_website`, `oro_price_list_customer_fb`, `oro_combined_price_list_to_website`, `oro_price_product_current`.
- **Checkout workflow dump**: for a checkout id, dump `oro_workflow_item`, `oro_checkout`, related `oro_shopping_list`, `oro_order`, customer group, and any project-specific workflow state.
- **Cart kit pipeline dump**: for a cart id, dump `oro_lab_cart`, `oro_lab_cart_line_item`, `oro_product_kit_item_line_item`, the cart-grid query result envelope, action_configuration JSON on serialized rows.

Each is a 5-minute investment that eliminates a 30–60 minute sequence of single-hypothesis runs.
