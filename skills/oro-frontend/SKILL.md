---
name: oro-frontend
description: "Use when developing OroCommerce v6.1 frontend — creating custom themes, overriding templates, writing SCSS styles, configuring layout updates, creating JavaScript page components, working with jsmodules.yml or assets.yml, or customizing storefront/back-office appearance. Relevant when the user mentions 'theme', 'SCSS', 'Twig layout', 'template override', 'page component', 'layout update', 'assets.yml', 'jsmodules.yml', 'storefront styling', 'back-office UI', or any OroCommerce frontend development task."
---

# OroCommerce v6.1 Frontend Development

## Theme Structure

Every OroCommerce theme lives in a bundle's `Resources/views/layouts/` directory:

```
YourBundle/Resources/
├── views/layouts/
│   └── [theme-name]/
│       ├── theme.yml              # REQUIRED — defines theme metadata
│       └── config/
│           ├── assets.yml         # CSS/JS registration
│           └── jsmodules.yml      # JavaScript module paths
└── public/[theme-name]/
    ├── scss/
    │   ├── settings/              # Mixins, functions (compiled FIRST)
    │   ├── variables/             # Config variables (compiled SECOND)
    │   └── components/            # Component styles (compiled THIRD)
    └── js/
```

## Theme Registration (theme.yml)

```yaml
label: My Custom Theme
description: "Optional description"
parent: default
groups: [commerce]
rtl_support: true
```

**Theme name constraint:** Must match regex `[a-zA-Z][a-zA-Z0-9_-:]*`. Use kebab-case. **Parent:** `default` for OroCommerce storefront. Child themes inherit SCSS, templates, and JavaScript.

## SCSS Organization — 3-Folder Compilation Order

Oro enforces a **strict compilation order**. Violating it causes build failures:
1. `settings/` — Mixins, functions, reusable utilities
2. `variables/` — Configuration variables, color palettes
3. `components/` — Component styles (BEM: `.block__element--modifier`)

A mixin defined in `components/` is unavailable to `variables/`. Use `!default` on variables so child themes can override. Use `map.deep-merge()` to extend parent palettes without losing keys.

See `references/frontend-patterns.md` for complete SCSS examples per folder.

## Layout Updates

Layout updates are YAML files that modify the layout tree at runtime:

```yaml
layout:
    actions:
        - '@add':
            id: my_custom_block
            parentId: page_main_content
            blockType: text
            options:
                text: 'Custom content here'
        - '@setBlockTheme':
            themes: 'my_custom_template.html.twig'
        - '@move':
            id: header_logo
            parentId: new_parent_id
            sibling: 2
        - '@remove':
            id: old_widget
```

See `references/layout-actions.md` for the full action reference.

## JavaScript Page Components

OroCommerce uses Chaplin.js-based page components initialized from server-rendered HTML via `data-page-component-module` and `data-page-component-options` attributes. Register modules in `jsmodules.yml` under `dynamic-imports:` for async loading.

See `references/frontend-patterns.md` for full Chaplin.js examples and `jsmodules.yml` configuration.

## Key Pitfalls

1. **SCSS folder order:** Defining a variable in `components/` won't work if `variables/` hasn't compiled yet. Follow the strict order: settings, variables, components.

2. **Block naming:** Twig layout blocks must end with `_widget`. ID-specific blocks use underscore prefix: `_product_details_widget`. Missing either breaks template resolution.

3. **Parent theme inheritance:** Use `map.deep-merge()` to extend parent color palettes. Direct assignment replaces the entire palette, losing parent keys.

## See Also

- `references/frontend-patterns.md` — SCSS examples, assets.yml, jsmodules.yml, Chaplin.js, template overrides, build commands, storefront vs. back-office
- `references/layout-actions.md` — Full layout action reference
- [v6.1 notes](references/v6.1.md) | [v7.0 notes](references/v7.0.md)
