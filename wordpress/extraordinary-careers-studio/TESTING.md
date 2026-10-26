# Release verification — 0.1.0

Verified on 26 October 2026 in an isolated WordPress Playground instance: WordPress 7.1.3, PHP 8.3.33. These checks do not establish compatibility with every supported WordPress/PHP version or the production theme stack.

## Executed checks

- 25/25 integration and containment assertions passed in actual WordPress. The suite is `tests/rest-tests.php` and runs through Playground's PHP runtime after activation. Run only on a disposable site: it creates synthetic users and posts.
- Missing nonce, anonymous requests and subscribers denied.
- Draft saving, mandatory publication fields, invalid email/date/status/ID handling verified.
- Publishing, updating, stale edit rejection, missing version rejection and accidental published-to-draft downgrade prevention verified.
- Unmanaged posts and other authors' posts protected; list permissions verified.
- Preview HTML, stored text sanitization and script/event-attribute rejection verified.
- Local mail and external WordPress HTTP were disabled; indexing disabled. Foreign Host and Origin rejected by the test harness.
- Public HTTP page returned the job content and plugin stylesheet.
- Browser cookie/nonce requests succeeded. A local demonstration completed all four steps, saved and reopened a draft, reviewed the preview and published to localhost only.
- Publish was disabled before review. Published-link control appeared after success.
- Desktop preview visually inspected. Narrow layout inspected at an actual 484-pixel viewport; no horizontal page overflow.
- Additional browser REST checks confirmed partial field preservation and expired preview/public output.
- JavaScript passed `node --check`.

## Remaining staging checks

Test installation and deactivation with the production Astra/Elementor/Spectra configuration, site-specific staff roles, cache behaviour after closing dates, and the existing careers listing. Native editor changes to managed content are retained on the public page, but subsequent Studio saves rebuild content from structured fields; use Studio consistently for these jobs. Exact simultaneous saves are not protected by a database transaction. No live-site installation or live vacancy publication was performed.

The minimum versions in the plugin header are declared targets, not a tested version matrix. Run a staging pilot before production use.
