# OroCommerce v6.1 Frontend — Version Notes

This document covers v6.1-specific behavior and notes for migration to future versions.

## v6.1 Specifics

### Symfony Version

OroCommerce v6.1 runs on **Symfony 5.4 LTS**. This affects:

- **Twig 3.x** — Template syntax and block resolution
- **Webpack 5** — Asset bundling (configured via `assets.yml`)
- **SASS 3.x** — SCSS compilation with modern syntax (e.g., `@use`, `@forward`)

### Sass Modules (@use / @forward)

v6.1 uses the modern Sass module system. Old `@import` syntax is deprecated but may still work in some cases.

**Correct (v6.1+):**
```scss
@use 'sass:map';
@use './variables' as vars;

.block {
    color: map.get(vars.$color-palette, 'primary', 'main');
}
```

**Deprecated (avoid):**
```scss
@import './variables';  // Don't use this in v6.1+
```

### Layout System

The layout system in v6.1:
- Uses YAML-based layout update files
- Supports block aliasing and inheritance
- Resolves block themes via the 3-level fallback (ID-specific → type-specific → parent)
- Supports layout caching (can be slow to clear)

### Theme Activation

Themes are activated via configuration in `config/config.yml`, not via the UI in v6.1. Changes require `cache:clear` to take effect.

### Asset Symlinks

Running `php bin/console assets:install --symlink` creates symlinks from `public/bundles/` to `src/[Bundle]/Resources/public/`. On Windows, symlinks may require admin privileges or WSL.

### CSS-in-JS / Component Styling

v6.1 does NOT use CSS-in-JS (no styled-components or Emotion). All styling is SCSS in the theme.

Components can add inline styles via Twig attributes:
```twig
<div class="my-component" style="{{ component_style | raw }}"></div>
```

But this is not recommended for maintainability.

## Known Issues & Workarounds

### Cache Invalidation on Theme Changes

After modifying `theme.yml` or `assets.yml`, always run:
```bash
php bin/console cache:clear
php bin/console oro:assets:build
```

Failing to clear cache can result in the old theme or assets being served. This is a common gotcha.

### Caching SCSS Compilation

SCSS files are compiled and cached. To force recompilation:
```bash
rm -rf var/cache/prod/
php bin/console cache:clear
```

### Symlink Issues on Windows

Windows may not support symlinks. Use `assets:install` without `--symlink` on Windows or WSL:
```bash
php bin/console assets:install
```

This copies assets instead of symlinking. Rebuilds are slower.

### Block Theme Not Applying

If a custom Twig template isn't being used, check:
1. `@setBlockTheme` action is present in layout YAML
2. Template file path is correct (relative to `Resources/views/layouts/[theme]/`)
3. Block type name matches the layout definition
4. Cache is cleared: `php bin/console cache:clear`

### RTL Support

Set `rtl_support: true` in `theme.yml` to enable right-to-left language support. Oro provides RTL-aware SCSS mixins and utilities. However, not all third-party packages are RTL-ready.

## Migration Path: v6.1 → v7.0

OroCommerce v7.0 is in development. Expected changes (not yet final):

### Likely Changes

- **Sass 4.x** — Experimental in Sass; may bring breaking changes
- **Webpack 6+** — Updated bundler (API changes)
- **Stimulus.js** — May replace Chaplin.js for JavaScript components
- **Tailwind CSS** — Possible integration or recommended approach
- **Layout System** — May evolve to support more modern patterns

### Recommended Practices for Future-Proofing

1. **Avoid hardcoding paths** — Use theme configuration and bundle prefixes
2. **Follow BEM naming** — Component isolation makes refactoring easier
3. **Modularize SCSS** — Don't create massive monolithic stylesheets
4. **Don't rely on internal Oro JS APIs** — Use public, documented interfaces
5. **Test responsive design** — Use standard media queries; avoid vendor-specific hacks

### Upgrade Path (Estimated)

When v7.0 releases, the migration may involve:
- Updating `theme.yml` structure
- Refactoring component JavaScript (Chaplin → Stimulus?)
- Rebuilding asset manifests
- Potential SCSS API changes (Sass 4.x)

Oro typically provides migration guides with major releases.

## Debugging & Tools

### Layout Debugging

List all blocks in the current layout:
```bash
php bin/console oro:debug:layout
```

Shows block hierarchy, types, and options.

### SCSS Debugging

Enable SCSS source maps for easier debugging:
```yaml
# config/config_dev.yml
assets:
    css_source_maps: true
```

### Template Debugging

Enable Twig debug in `config_dev.yml`:
```yaml
twig:
    debug: true
    strict_variables: true
```

Then view the page source and look for comments like:
```html
<!-- TEMPLATE: bundles/acmedemo/theme/product_view.html.twig -->
```

### Asset Building

Monitor asset builds with verbose output:
```bash
php bin/console oro:assets:build --verbose
```

## Performance Notes

### CSS/JS Minification

v6.1 minifies CSS and JavaScript in production mode automatically. In dev mode, files are not minified for easier debugging.

### CSS Preprocessing

SCSS compilation is cached. The first build is slow; subsequent builds are faster. Caching is keyed on file hashes.

### JavaScript Modules

Lazy-loading JavaScript modules (via `jsmodules.yml`) reduces initial page load. Only modules referenced by `data-page-component-module` are loaded.

## Resources

- **Official Oro Documentation:** https://doc.oroinc.com/ (update version selector to 6.1)
- **GitHub Discussions:** https://github.com/oroinc/orocommerce/discussions/
- **Slack Community:** #orocommerce-dev (if available)

## Contact & Support

For issues specific to v6.1 frontend development, consult:
- Oro's official documentation (version 6.1)
- Community forums and GitHub issues
- Your organization's Oro support team (if Enterprise)
