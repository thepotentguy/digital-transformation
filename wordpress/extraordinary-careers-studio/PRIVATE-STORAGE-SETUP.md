# Private application storage setup

Version 0.2.0 deliberately disables production CV intake until hosting configuration is complete. Do not use real applicants during setup.

## Configure the existing site

1. Back up the WordPress database and files. Activate the plugin on staging. When upgrading the original 0.1.0 pilot, deactivate and reactivate once to create application tables and assign the administrator capability. No job posts are removed.
2. Have the hosting administrator create a directory outside the public document root, WordPress directory and content directory. It must be writable only by the website's service account as appropriate. Verify that no virtual host, alias, static-file handler or symbolic link exposes it. The plugin rejects public-root paths and symlinks, but cannot discover every web-server alias.
3. Add the following constants to the site's private configuration, replacing the example values. Do not commit site configuration or credentials to GitHub.

```php
define('ECS_CV_STORAGE_DIR', '/private/path/approved-by-your-host');
define('ECS_CV_STORAGE_PRIVATE', true); // Set only after verifying there is no public URL.
define('ECS_CV_PRIVACY_NOTICE', 'Insert the HR-approved application collection notice here.');
define('ECS_CV_UPLOADS_ENABLED', true);
```

4. Connect the site's document scanner through the `ecs_cv_scan_file` filter. It receives a default `WP_Error` and the temporary uploaded file path. Return exactly `true` only after a successful clean result. Return a `WP_Error` for rejection, scanner failure or unavailable service. Do not install a filter that unconditionally returns true. No scanner provider or paid service is bundled.
5. Confirm **HR Applications** reports service availability, and submit a synthetic PDF. Test a deliberately rejected scanner result, unauthorised direct download, application expiry and deletion. Check file and database recovery on staging.
6. Grant private HR access only to designated users through their WordPress user profiles. Test with a normal publisher to confirm that publishing jobs does not expose applications.
7. Enable **online applications** in the relevant job's Applications step and review the public page. The default enquiries address is `HR@extraordinary.co.za`; website application storage does not read or send email to that mailbox.

## Retention and operation

The owner selected twelve calendar months from application. Expired CV download/status changes are blocked. The HR dashboard shows expired records awaiting deletion. Each confirmed cleanup removes up to 100 expired applications and their files; repeat deliberately if more remain. Manual deletion also requires confirmation. No scheduler is installed. Assign an HR owner to perform expiry cleanup; do not interpret a disabled download as proof that stored bytes have been deleted.

Include both the private directory and application tables in the hosting backup policy. Document backup retention separately and ensure restored copies reapply prior deletions. Audit rows record actor/action/reference/time without CV content; define an appropriate audit retention policy before production use.

PHP must support fileinfo and secure upload handling. The host's request/upload limits must allow a 5 MB document plus multipart overhead. Quotas, reliable backups, scanning and availability are hosting responsibilities. Failed scanning or storage checks must remain visible; do not bypass them to enable intake.

## Local test mode

The isolated test harness uses synthetic PDFs and explicitly declares a local environment. `ECS_CV_TEST_MODE` is accepted only with WordPress environment `local` or `development`. Never configure test mode on production. Passing local upload tests does not prove that a production scanner, private directory or backup policy is installed.

## Recovery and removal

Deactivation retains records and private files and removes the collection/retrieval UI. It does not delete applicant records. Before removing the plugin permanently, arrange HR-authorised retention/export/deletion and verify both stores. Do not move CVs into public uploads to make downloads work.
