# WP AyeCode Template Manager

A centralized template management screen for the AyeCode WordPress plugin ecosystem. This package is pulled into AyeCode plugins (GeoDirectory, UsersWP, Invoicing) as a Composer dependency and gives users one place, Appearance > Templates, to see every template the plugins use.

## Overview

For each template the screen answers "what renders this and where do I edit it":

- **Block themes**: routes to the Site Editor template. Customized means a saved copy exists; Restore removes it (the same as the Site Editor's own Reset).
- **Classic themes**: routes to the page in whichever builder built it. Customized means the content differs from the plugin default; Restore rewrites the default.
- **Theme builders** (Elementor Pro, Bricks, Divi Theme Builder): when a builder template's condition matches the post type, the row says so and Edit opens that template instead.

Other features:

- Missing pages are shown, not hidden, with a Recreate action (untrashes when possible).
- Per post type copies of a template through Add Template. Only these copies can be deleted.
- Tidy Pages list: layout pages leave the "All" view and get a "Templates" view, and every managed page carries a label like "GeoDirectory: Single Layout".
- Built on the AyeCode Settings Framework list table.

## Usage as Composer Package

See `docs/plugin-integration-guide.md` for the registration filter and the callbacks a host plugin implements.

## Requirements

- WordPress 6.0+
- PHP 7.4+
- AyeCode Settings Framework
