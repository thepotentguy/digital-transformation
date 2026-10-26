# Release verification — 0.2.0

Verified on 26 October 2026 in an isolated WordPress Playground instance: WordPress 7.1.3, PHP 8.3.33. These checks do not establish compatibility with every supported WordPress/PHP version or the production theme stack.

## Application and image extension

97 automated assertions passed on the extension: 33 core/image checks, 42 CV HTTP checks and 22 deterministic failure checks. The 42 CV checks were rerun after the failure-recovery fix and passed again.

- Real HTTP multipart PDF upload, replay without duplicate storage, file type/signature/size/scanner rejection, consent/token validation and closed/unmanaged/disabled role rejection.
- Actual authenticated PDF response and byte match, author denial, HR-only user access without publishing privileges, guessed private-path denial, and expired download refusal.
- Twelve-calendar-month expiry, private file mode, search/status controls, record/file deletion and manual expiry purge.
- Database insert/finalization and file-move failure handling, tracked incomplete uploads if cleanup cannot remove bytes, no download/status completion of pending records, and no false contact-count success on a failed audit insert. The failed-unlink case used a directory obstruction simulation.
- Real image attachment validation, featured image assignment, missing alt text rejection, and inaccessible/non-image ID rejection.
- Browser checks: WordPress image picker selected a fixture; preview displayed the image and disabled online-form representation; reviewed publication updated the local role. The actual public form submitted a synthetic PDF and returned an opaque receipt; HR displayed that application with a twelve-month expiry and protected download. A controlled HR mail-link click incremented the separate counter without opening an email client. The final HR layout has no horizontal page overflow at a 484-pixel viewport; the wide results table scrolls within its panel.

The synthetic scanner accepts only exact fixture bytes. This does not test a real malware scanner. An initial test-runner auto-login redirect and a subsequent WordPress startup network timeout were resolved in the harness by supplying its normal local cookie marker and pinning the previously verified WordPress version. They did not establish production failures. One synthetic browser-demo application remains in the ephemeral local instance; no real CVs were used or published.

Test source files in `tests/` are intended for an isolated Playground runtime. They create synthetic users/posts and exercise deletion and failure injection. Never run them on a live site. Runtime configuration and results remain outside the installable ZIP.

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

Test installation and deactivation with the production Astra/Elementor/Spectra configuration, site-specific staff roles, cache behaviour after closing dates, and the existing careers listing. Verify actual private directory/web-server aliases, scanner integration, upload limits, backup/restore, HR notice and expiry-cleanup ownership before collecting real CVs. Native editor changes to managed content are retained on the public page, but subsequent Studio saves rebuild content from structured fields; use Studio consistently for these jobs. Exact simultaneous job edits are not protected by a database transaction. No live-site installation or live vacancy publication was performed.

The minimum versions in the plugin header are declared targets, not a tested version matrix. Run a staging pilot before production use.
