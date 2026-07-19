# Customisations

This plugin is a local custom fork of Twelve Step Meeting List for AA Canberra use. It is not intended to receive upstream WordPress plugin updates.

## Local Plugin Identity

- Changed the plugin name from `12 Step Meeting List` to `AA Canberra Meeting List`.
- Changed the plugin description to `Customised version of Twelve Step Meeting List, developed by Code4Recoery`.
- Changed the plugin author to `Hamish`.
- Changed the plugin version to `3.19.12-aacanberra.1`.
- Left the text domain as `12-step-meeting-list` so existing strings, options, and translations keep working.

## Local Assets

- Replaced the externally hosted TSML UI script with local `assets/js/app.js`.
- Replaced the externally hosted Unsplash online meeting image with local `assets/img/online-image.jpeg`.
- Updated source and built block metadata to use the local online meeting image.
- Updated compiled legacy public CSS to use the local online meeting image.
- Updated the TSML UI bundle's online meeting background image to use the local image.

## TSML UI Links

- Added two public-facing printable meeting list links for the TSML UI via the `AA_CANBERRA_TSML_UI_CUSTOM_LINKS` PHP constant.
- The links are passed through `tsml_react_config.custom_links` and placed below the TSML UI search/filter controls.
- Feedback notification emails and TSML UI mailto links render meeting links under the `AA_CANBERRA_TSML_UI_FEEDBACK_PUBLIC_ORIGIN` PHP constant.
- The local `assets/js/app.js` enqueue uses the file modified time as its cache-busting version.
- Real TSML UI source is bundled under `tools/tsml-ui`, pinned to upstream Code4Recovery commit `fef58db87ded610e1660fa246d48e374f11b54b5`.
- Run `npm run build:tsml-ui` from WSL to rebuild `assets/js/app.js` and `assets/js/app.js.map`.
- Run `npm run build:tsml-ui:readable` from WSL when `assets/js/unminified_app.js` also needs refreshing.
- `assets/js/unminified_app.js` is a readable generated bundle for audit/debugging only; edit `tools/tsml-ui/src` instead.

## Meeting Admin Lock

- Locked direct editing and creation of `tsml_meeting` posts in wp-admin because meetings are maintained by an external feed.
- The Meetings, Regions, and Districts lists remain visible for review and filtering.
- The Add New button/form, row edit/delete/trash actions, title edit links, and bulk actions are hidden on the locked admin lists.
- Direct access to meeting edit screens redirects back to the Meetings list with an admin notice.
- Import, cron, and WP-CLI update paths remain available so the external feed can continue to manage meeting records.

## Dashboard Address Settings

- Removed the full TSML Settings submenu at `/wp-admin/edit.php?post_type=tsml_meeting&page=settings`.
- Direct access to the removed Settings URL redirects to the WordPress dashboard.
- Added a dashboard widget for managing feedback email addresses and change notification email addresses only.
- Feedback notification emails and TSML UI mailto links render meeting links under `https://meetings.aa.org.au/` instead of the local WordPress domain.
- Removed the original TSML dashboard contribution/help widget.
- Hardcoded removed settings to Alcoholics Anonymous, kilometres, public contact visibility, Australia/Sydney timezone, TSML UI, and automatic imports on.
- Removed the manual CSV import, Example CSV, and Automatic Imports controls from the Import & Export screen.
