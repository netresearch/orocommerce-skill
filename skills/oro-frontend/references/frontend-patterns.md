# Frontend Patterns Reference

## SCSS Detailed Examples

### Settings (Mixins & Functions)

```scss
// scss/settings/primary-settings.scss
@use 'sass:math';

@mixin button-base {
    display: inline-block;
    padding: math.div(16px, 16px) * 1rem;
    border-radius: 4px;
}

@mixin flex-center {
    display: flex;
    align-items: center;
    justify-content: center;
}
```

### Variables (Palette & Config)

```scss
// scss/variables/primary-variables.scss
@use 'sass:map';

$spacing-unit: 8px;
$border-radius: 4px;

// Deep merge with parent palette
$theme-color-palette: (
    'primary': (
        'main': #37435c,
        'light': #5f6b8f,
        'dark': #2a3347,
    ),
    'secondary': (
        'c1': #fcb91d,
        'c2': #f5a623,
    ),
) !default;

$color-palette: map.deep-merge($color-palette, $theme-color-palette) !default;
```

Use `!default` to let child themes override. Use `map.deep-merge()` to extend parent palettes without losing their keys.

### Components (BEM Naming)

```scss
// scss/components/button.scss
@use '../settings/primary-settings' as settings;
@use '../variables/primary-variables' as vars;

.button {
    @include settings.button-base;
    color: map.get(vars.$color-palette, 'primary', 'main');

    &__icon {
        margin-right: 0.5rem;
    }

    &__icon--right {
        margin-right: 0;
        margin-left: 0.5rem;
    }

    &--primary {
        background-color: map.get(vars.$color-palette, 'primary', 'main');
        color: white;
    }

    &--secondary {
        background-color: transparent;
        border: 1px solid map.get(vars.$color-palette, 'primary', 'main');
    }
}
```

Use BEM: `.block__element--modifier`. This makes component boundaries clear and nesting predictable.

## Assets Configuration (assets.yml)

Register CSS and JavaScript files that the build system processes:

```yaml
# config/assets.yml
css:
    inputs:
        - 'bundles/acmedemo/my-theme/scss/settings/primary-settings.scss'
        - 'bundles/acmedemo/my-theme/scss/variables/primary-variables.scss'
        - 'bundles/acmedemo/my-theme/scss/components/button.scss'
        - 'bundles/acmedemo/my-theme/scss/components/form.scss'

js:
    inputs:
        - 'bundles/acmedemo/my-theme/js/app.js'
```

To **remove** an inherited asset: use `~` (null key):

```yaml
css:
    inputs:
        ~: 'bundles/parent/parent-theme/scss/old-file.scss'
```

To **replace** an asset: map the old path to a new one:

```yaml
css:
    inputs:
        'bundles/parent/parent-theme/scss/button.scss': 'bundles/acmedemo/my-theme/scss/button.scss'
```

## JavaScript Module Configuration (jsmodules.yml)

This file tells the build system which JavaScript modules can be dynamically loaded on pages:

```yaml
dynamic-imports:
    acmedemo:
        - acmedemo/js/components/product-reviews
        - acmedemo/js/components/image-gallery
        - acmedemo/js/views/search-form
```

Each entry should be a valid module path. The build system creates async imports for these, allowing pages to load only the JavaScript they need.

## Chaplin.js Page Components

OroCommerce uses Chaplin.js-based page components. A page component is a Backbone.View that initializes with data from the server-rendered HTML.

**HTML markup:**

```html
<div data-page-component-module="acmedemo/js/components/product-reviews"
     data-page-component-options='{"product_id": 42, "sort": "newest"}'>
</div>
```

**JavaScript component:**

```javascript
// js/components/product-reviews.js
import Chaplin from 'chaplin';

export default Chaplin.View.extend({
    initialize() {
        this.options = this.options || {};
        this.productId = this.options.product_id;
        this.sort = this.options.sort || 'newest';

        this.render();
    },

    render() {
        this.$el.html(`<p>Reviews for product ${this.productId}</p>`);
    }
});
```

Register the component in `jsmodules.yml` for dynamic loading.

## Twig Block Naming & Resolution

Oro layouts use blocks to render content. Block naming follows a strict resolution order:

1. `{% block _<block_id>_widget %}` — ID-specific (highest priority)
2. `{% block <block_type>_widget %}` — Type-specific
3. `{% block <parent_block_type>_widget %}` — Parent block fallback

All block names end with `_widget`. ID-specific blocks start with underscore: `_product_view_widget`.

**Example:**

```twig
{# If block_id is 'product_details' and block_type is 'product_view' #}

{# Highest priority: #}
{% block _product_details_widget %}...{% endblock %}

{# Fall back to type: #}
{% block product_view_widget %}...{% endblock %}

{# Fall back to parent (e.g., container): #}
{% block container_widget %}...{% endblock %}
```

## Twig Template Overrides

Override a bundle's template without modifying the original:

```
templates/bundles/[BundleName]/[OriginalPath]/[Template]
```

**Example:** To override `OroProductBundle/Resources/views/Product/view.html.twig`:

```
templates/bundles/OroProductBundle/Product/view.html.twig
```

Symfony's bundle override mechanism automatically uses this instead of the original. No extra configuration needed.

## Storefront vs. Back-Office

**Storefront:**
- Server-rendered, not a single-page app
- SEO-optimized (traditional form submissions)
- Don't use client-side routing (Backbone.Router)
- Page components are lightweight, augmenting server HTML

**Back-Office:**
- SPA-like experience with Chaplin routing
- Client-side navigation within the app
- Heavier JavaScript, form handling via AJAX
- Styling uses the same theme system but serves different purposes

Use appropriate patterns for each context. A storefront theme should not implement client-side routing.

## Build & Asset Installation

After modifying SCSS, templates, or JavaScript:

```bash
# Clear Symfony cache
php bin/console cache:clear

# Install/symlink assets to public/
php bin/console assets:install --symlink

# Build CSS/JS from assets.yml
php bin/console oro:assets:build
```

The build command reads `assets.yml`, compiles SCSS in folder order, and outputs minified CSS and JavaScript to the public directory.
