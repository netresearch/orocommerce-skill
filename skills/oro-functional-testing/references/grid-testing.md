# Functional Test — Grid Testing

Datagrids in Oro are rendered server-side as JSON and hydrated client-side. The test framework exposes `requestGrid()` on the browser client so tests can hit grids by name, apply filters, and assert on rows without parsing HTML.

## Method Signature

```php
$response = $this->client->requestGrid(
    string $gridName,
    array $gridParams = [],
    bool $isRealRequest = false,
    array $serverOptions = []
);
```

- `$gridName` — the grid's identifier as declared in `datagrids.yml` (e.g. `users-grid`, `acme-demo-documents-grid`).
- `$gridParams` — flat key/value array with **bracketed filter paths** (see below).
- `$isRealRequest` — `false` dispatches through the kernel, `true` hits the actual HTTP layer. Leave default unless testing middleware.
- `$serverOptions` — optional auth header for ACL tests.

## Filter Bracket Notation

Grid filter parameters use bracketed paths that mirror the HTML form field names. The format is:

```
{gridName}[_filter][{fieldName}][value]
{gridName}[_filter][{fieldName}][type]
```

Example — filter `users-grid` by `username` equal to `admin`:

```php
$response = $this->client->requestGrid('users-grid', [
    'users-grid[_filter][username][value]' => 'admin',
]);
```

Example — range filter on a price column:

```php
$response = $this->client->requestGrid('acme-demo-documents-grid', [
    'acme-demo-documents-grid[_filter][price][value][start]' => 100,
    'acme-demo-documents-grid[_filter][price][value][end]'   => 500,
    'acme-demo-documents-grid[_filter][price][type]'         => 7, // between
]);
```

The filter `type` integer values come from the filter type registered in the grid — check `FilterUtility::TYPE_*` constants or the grid definition when uncertain.

## Extracting Results

```php
$response = $this->client->requestGrid('users-grid', [
    'users-grid[_filter][username][value]' => 'admin',
]);

$result = $this->getJsonResponseContent($response, 200);
self::assertCount(1, $result['data']);

$firstRow = reset($result['data']);
self::assertSame('admin@example.com', $firstRow['email']);
```

`getJsonResponseContent($response, 200)` asserts the status and returns the decoded body. The grid response shape is:

```
{
  "data":    [ { column: value, ... }, ... ],
  "options": { "totalRecords": N, ... },
  "metadata": { ... }
}
```

Use `$result['options']['totalRecords']` to assert pagination totals; use `$result['data']` for row-level assertions. The row keys are the column `name` values from `datagrids.yml`, not the underlying DB columns.

## Sorting

```php
$response = $this->client->requestGrid('users-grid', [
    'users-grid[_sort_by][createdAt]' => 'DESC',
]);
```

## Pagination

```php
$response = $this->client->requestGrid('users-grid', [
    'users-grid[_pager][_page]'     => 2,
    'users-grid[_pager][_per_page]' => 10,
]);
```
