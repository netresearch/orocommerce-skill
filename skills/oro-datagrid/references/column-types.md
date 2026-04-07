# OroCommerce v6.1 Datagrid Column & Filter Types Reference

## Column Types

### string
Basic text rendering. No special formatting.
```yaml
columns:
    title:
        label: Title
        type: string
```

### integer
Numeric integers. Right-aligned by default.
```yaml
columns:
    count:
        label: Count
        type: integer
```

### boolean
True/false rendered as checkmark (✓) or X.
```yaml
columns:
    active:
        label: Active
        type: boolean
```

### date
Dates in YYYY-MM-DD format. Requires DateTime entity property.
```yaml
columns:
    createdAt:
        label: Created
        type: date
        frontend_type: date
```

### datetime
Full timestamp YYYY-MM-DD HH:MM:SS.
```yaml
columns:
    updatedAt:
        label: Updated
        type: datetime
        frontend_type: datetime
```

### decimal
Floating-point with configurable precision.
```yaml
columns:
    amount:
        label: Amount
        type: decimal
        frontend_type: decimal
```

### percent
Percentage display (value rendered as %).
```yaml
columns:
    completion:
        label: % Complete
        type: percent
```

### currency
Formatted with currency symbol (USD $, EUR €, etc.). Requires `currency_code` option.
```yaml
columns:
    price:
        label: Price
        type: currency
        options:
            currency_code: USD
```

### html
Raw HTML rendering. **Use sparingly & never with user input.**
```yaml
columns:
    description:
        label: Description
        type: html
        safe: true  # Sanitize HTML if sourced from untrusted data
```

### link
Hyperlink. Requires `route` or `url` option.
```yaml
columns:
    document_link:
        label: Document
        type: link
        route: acme_demo_document_view
        routeParameters:
            id: $.id
```

### twig
Render column value via Twig template.
```yaml
columns:
    status_badge:
        label: Status
        type: twig
        template: '@AcmeDemoBundle/datagrid/status_badge.html.twig'
        context:
            status: $.status
            class: $.statusClass
```

### phone
Phone number formatting with country code detection.
```yaml
columns:
    phone:
        label: Phone
        type: phone
```

### image
Display image thumbnail.
```yaml
columns:
    thumbnail:
        label: Image
        type: image
        width: 100
        height: 100
```

### color
Display a color swatch.
```yaml
columns:
    color:
        label: Color
        type: color
```

---

## Filter Types

### string
Text search. Case-insensitive substring match.
```yaml
filters:
    columns:
        subject:
            type: string
            data_name: d.subject
            label: Subject
```

### integer
Filter by exact integer or range.
```yaml
filters:
    columns:
        count:
            type: integer
            data_name: d.count
```

### boolean
Filter by true/false.
```yaml
filters:
    columns:
        active:
            type: boolean
            data_name: d.active
```

### date
Date range filter (from–to).
```yaml
filters:
    columns:
        created:
            type: date
            data_name: d.createdAt
```

### datetime
Datetime range filter.
```yaml
filters:
    columns:
        updated:
            type: datetime
            data_name: d.updatedAt
```

### decimal
Decimal range filter.
```yaml
filters:
    columns:
        amount:
            type: decimal
            data_name: d.amount
```

### entity
Filter by related entity. Requires `field_options`.
```yaml
filters:
    columns:
        author:
            type: entity
            data_name: d.author
            options:
                field_options:
                    class: Acme\Bundle\DemoBundle\Entity\User
                    property: name
                    choice_label: name
```

### choice
Dropdown filter with predefined options.
```yaml
filters:
    columns:
        status:
            type: choice
            data_name: d.status
            options:
                field_options:
                    choices:
                        new: New
                        active: Active
                        closed: Closed
```

### multiselect
Multi-select filter. Returns items matching ANY selected value.
```yaml
filters:
    columns:
        categories:
            type: multiselect
            data_name: d.categories
            options:
                field_options:
                    choices:
                        1: Category A
                        2: Category B
                        3: Category C
```

### exclusion
Special filter for excluding specific items. Rarely used.
```yaml
filters:
    columns:
        exclude_ids:
            type: exclusion
            data_name: d.id
```

---

## Filter Options Reference

### data_name (Required)
The entity property path for filtering. Must match query alias.
```yaml
data_name: d.subject
```

### label (Optional)
Filter display label. Defaults to property name.
```yaml
label: Document Subject
```

### field_options (Optional)
Symfony form field options.
```yaml
field_options:
    class: Entity\Class
    property: displayProperty
    choice_label: displayProperty
    multiple: true
```

### operator (Optional)
Comparison operator. Default: `=` for most, `LIKE` for strings.
```yaml
operator: LIKE
```

### case_insensitive (Optional)
For string filters. Default: true.
```yaml
case_insensitive: true
```

### renderable (Optional)
Show filter in UI. Default: true.
```yaml
renderable: true
```

---

## Common Column Options

### label (Required)
Column header text.
```yaml
label: Document Subject
```

### data_name (Optional)
Property path. Auto-inferred from column key if omitted.
```yaml
data_name: d.subject
```

### type (Optional)
Column type. Default: string.
```yaml
type: decimal
```

### frontend_type (Optional)
Explicit frontend type override.
```yaml
frontend_type: date
```

### editable (Optional)
Enable inline editing on this column.
```yaml
editable: true
```

### sortable (Optional)
Enable sorting. Requires entry in `sorters` section.
```yaml
sortable: true
```

### renderable (Optional)
Show/hide column. Default: true.
```yaml
renderable: true
```

### width (Optional for image/color)
CSS width. Example: "100px".
```yaml
width: 100px
```

### align (Optional)
Text alignment: left, center, right. Default: left.
```yaml
align: right
```

---

## Example: Multi-Type Grid

```yaml
datagrids:
    document_list:
        source:
            type: orm
            query:
                select: [d]
                from: [{ table: Acme\Bundle\DemoBundle\Entity\Document, alias: d }]

        columns:
            id:
                label: ID
                type: integer
                align: right
            subject:
                label: Subject
                type: string
            status:
                label: Status
                type: choice
            amount:
                label: Amount
                type: currency
                options:
                    currency_code: USD
            active:
                label: Active
                type: boolean
            createdAt:
                label: Created
                type: datetime
            actions:
                label: Actions
                type: action

        filters:
            columns:
                subject:
                    type: string
                    data_name: d.subject
                status:
                    type: choice
                    data_name: d.status
                    options:
                        field_options:
                            choices:
                                new: New
                                active: Active
                                closed: Closed
                amount:
                    type: decimal
                    data_name: d.amount
                active:
                    type: boolean
                    data_name: d.active
                createdAt:
                    type: datetime
                    data_name: d.createdAt

        sorters:
            columns:
                id:
                    data_name: d.id
                subject:
                    data_name: d.subject
                amount:
                    data_name: d.amount
                createdAt:
                    data_name: d.createdAt
            default:
                createdAt: DESC

        actions:
            view:
                type: navigate
                label: View
                icon: eye
                link: acme_demo_document_view
                rowAction: true
            edit:
                type: navigate
                label: Edit
                icon: pencil
                link: acme_demo_document_update
```

---

## Performance Notes

- **String filters:** LIKE queries are slow on large datasets. Index `data_name` columns in database.
- **Entity filters:** Each filter triggers a query to load choices. Cache if choices are static.
- **Datetime filters:** Always use indexed columns for `createdAt`, `updatedAt`.
- **Joined columns:** Avoid filters/sorters on joined relationships. Use `onResultAfter` to attach data instead.
