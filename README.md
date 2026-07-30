# AA Canberra Meeting List

AA Canberra Meeting List is a customised WordPress plugin for publishing and maintaining public meeting information for Canberra and the surrounding districts of Alcoholics Anonymous on [aacanberra.org](https://aacanberra.org). It is a local fork of [Twelve Step Meeting List](https://github.com/code4recovery/12-step-meeting-list), designed to receive automatic upstream meeting updates from [meetings.aa.org.au](https://meetings.aa.org.au).

The plugin powers the public meeting directory on [aacanberra.org](https://aacanberra.org) while remaining compatible with upstream TSML meeting data imports.

## Disclaimer

This plugin is maintained specifically for the Canberra & District Central Service Office and is not intended as a drop-in replacement for the upstream Twelve Step Meeting List plugin.

## Features

- Registers and displays meeting, location, group, region, and district data using the Twelve Step Meeting List data model.
- Serves the public meeting directory through the bundled TSML UI, with custom links for printable meeting lists and meeting update information.
- Imports and automatically synchronises meeting data from external JSON feeds using the TSML import buffer.
- Exposes meeting data through AJAX and the REST endpoint at `/wp-json/tsml/meetings` when sharing settings allow it.
- Provides public meeting feedback forms that email configured recipients, with nonce checks, honeypot protection, timing checks, rate limits, real meeting validation, and optional Cloudflare Turnstile integration.
- Locks direct meeting, region, and district editing in wp-admin so feed-owned records are reviewed in WordPress but maintained by the external feed.
- Replaces the full TSML settings page with a dashboard widget for feedback and change-notification email addresses.

## AA Canberra Defaults

This fork fixes the following TSML settings to match AA Canberra's purpose:

- **Program:** Alcoholics Anonymous
- **Distance units:** kilometres
- **Timezone:** Australia/Sydney
- **User interface:** TSML UI
- **Contact display:** public
- **Automatic imports:** enabled

See `CUSTOMISATIONS.md` for the detailed list of local changes from upstream.

## Key Files

- `AA Canberra Meeting List.php` - plugin bootstrap, constants, includes, and activation hooks.
- `includes/init.php` - public template routing and core runtime hooks.
- `includes/functions_import.php` - external feed import and buffered meeting updates.
- `includes/ajax.php` - AJAX endpoints, CSV export, and meeting feedback handlers.
- `includes/rest.php` - public REST feed endpoint.
- `includes/admin_lock.php` - wp-admin locks for feed-owned meeting records and taxonomies.
- `includes/admin_settings.php` - dashboard-only email address settings.
- `tools/tsml-ui` - source for the bundled React TSML UI.
- `assets/js/app.js` - generated public TSML UI bundle.

## Development Notes

Edit PHP source files under `includes/` and TSML UI source under `tools/tsml-ui/src`. Generated JavaScript bundles should not be edited directly except for emergency debugging.

Useful build commands:

```bash
npm run build:tsml-ui
npm run build:tsml-ui:readable
npm run build:wp
```

Cloudflare Turnstile is enabled for meeting feedback forms when both `CF_TURNSTILE_SITE_KEY` and `CF_TURNSTILE_SECRET_KEY` are defined in `wp-config.php`.

## License

GPLv2 or later. See `LICENSE.txt`.
