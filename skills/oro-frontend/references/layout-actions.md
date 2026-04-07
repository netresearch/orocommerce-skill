# Layout Actions Reference — OroCommerce v6.1

Layout updates use actions to modify the layout tree at runtime. Actions are YAML entries in `layout:` sections. This reference covers all standard layout actions with examples.

## @add

Add a new block to the layout.

```yaml
- '@add':
    id: my_block
    parentId: page_main_content
    blockType: text
    options:
        text: 'Block content'
    sibling: 0  # Optional: position among siblings (0=first)
```

**Parameters:**
- `id` (required): Unique block identifier
- `parentId` (required): Parent block ID
- `blockType` (required): Block type (e.g., 'text', 'container', 'product_list')
- `options` (optional): Block-specific options (varies by block type)
- `sibling` (optional): Position among siblings (0-based, default=append)

## @remove

Remove a block and its children from the layout.

```yaml
- '@remove':
    id: old_widget
```

**Parameters:**
- `id` (required): Block ID to remove

Removing a parent block removes all children. This is useful for disabling inherited blocks from parent themes.

## @move

Relocate a block to a different parent or position.

```yaml
- '@move':
    id: header_logo
    parentId: new_parent
    sibling: 2
```

**Parameters:**
- `id` (required): Block ID to move
- `parentId` (optional): New parent block ID (if not specified, parent unchanged)
- `sibling` (optional): New position among siblings

The block and its children are moved intact. This is useful for rearranging layout structure without duplicating blocks.

## @addTree

Add multiple blocks in a hierarchy in a single action.

```yaml
- '@addTree':
    node:
        parentId: page_main_content
        blockType: container
        options:
            attr:
                class: 'my-section'
        children:
            - blockType: text
              options:
                  text: 'Subsection title'
            - blockType: container
              id: details_container
              children:
                  - blockType: text
                    options:
                        text: 'Details content'
```

**Parameters:**
- `node` (required): Root node definition
  - `parentId` (required): Parent block ID
  - `blockType` (required): Block type
  - `options` (optional): Block options
  - `children` (optional): Array of child node definitions (recursive)

Each child node uses the same structure. IDs are auto-generated if not specified.

## @setOption

Update an existing block's options.

```yaml
- '@setOption':
    id: my_block
    optionName: text
    optionValue: 'Updated content'
```

**Parameters:**
- `id` (required): Block ID
- `optionName` (required): Option key
- `optionValue` (required): New value

## @appendOption

Append a value to an existing option (useful for arrays/lists).

```yaml
- '@appendOption':
    id: product_attributes
    optionName: attributes
    optionValue:
        name: size
        label: 'Product Size'
```

**Parameters:**
- `id` (required): Block ID
- `optionName` (required): Option key (should be an array)
- `optionValue` (required): Value to append

## @setBlockTheme

Set the Twig template file(s) for rendering blocks.

```yaml
- '@setBlockTheme':
    themes: 'my_custom_template.html.twig'
    blocks: [ block_one, block_two ]  # Optional: specific blocks only
```

**Parameters:**
- `themes` (required): Template file name or path
- `blocks` (optional): Array of specific block type names. If omitted, applies to all blocks.

The template file is resolved relative to the current bundle's `Resources/views/layouts/[theme]/` directory.

## @configure

Set multiple options on a block at once.

```yaml
- '@configure':
    id: my_block
    options:
        text: 'New text'
        attr:
            class: 'highlight'
            data-id: '123'
```

**Parameters:**
- `id` (required): Block ID
- `options` (required): Object/hash of options to set

This is a convenience action for setting many options without repeated `@setOption` calls.

## Example: Complete Layout Update

```yaml
layout:
    actions:
        # Add a container to hold custom content
        - '@add':
            id: custom_section
            parentId: page_main_content
            blockType: container
            options:
                attr:
                    class: 'custom-section'

        # Set the theme for this container
        - '@setBlockTheme':
            themes: 'my_templates.html.twig'
            blocks: [ container ]

        # Add content inside
        - '@add':
            id: section_title
            parentId: custom_section
            blockType: text
            options:
                text: 'Special Offers'

        - '@add':
            id: offers_list
            parentId: custom_section
            blockType: product_list
            options:
                product_ids: [ 1, 2, 3 ]

        # Remove an inherited block
        - '@remove':
            id: sidebar_ads

        # Move a block to a new location
        - '@move':
            id: footer_links
            parentId: custom_section
            sibling: 1

        # Update an option on an existing block
        - '@setOption':
            id: page_title
            optionName: title
            optionValue: 'Our Products'
```

## Block Types (Common)

Not exhaustive; custom block types are defined by bundles.

- `container` — Generic container (no rendering, wraps children)
- `text` — Static text content
- `product_view` — Product detail page
- `product_list` — List of products
- `form` — Symfony form rendering
- `breadcrumbs` — Navigation breadcrumb trail
- `widget` — Generic widget block

## Conditions (v6.1)

Some layout systems support conditional actions. While not shown above, Oro's layout engine may support conditions for advanced use cases. Refer to the bundle-specific documentation for conditional block rendering.

## Order Matters

Actions execute in the order specified. Depending on a block that hasn't been added yet will fail. Generally, follow this pattern:

1. `@add` all blocks
2. `@setBlockTheme` to define rendering
3. `@setOption` / `@appendOption` to customize
4. `@remove` to delete inherited blocks (place early if dependencies exist)
5. `@move` to rearrange (place last to avoid dependency issues)

## Debugging Layout Updates

To see the final layout tree after all actions:

```bash
php bin/console oro:debug:layout
```

This helps identify which blocks exist, their parents, and their options.
