# Extraordinary Careers Studio

A WordPress plugin that guides staff through creating consistent career posts, with job images, previews, publication and an optional private application inbox for HR.

## Installation

1. Back up the staging WordPress site.
2. Upload `extraordinary-careers-studio.zip` through **Plugins > Add New > Upload Plugin** and activate it.
3. Open **Careers Studio** in the WordPress menu.
4. Create a draft, complete the four steps, and review the desktop and mobile previews.
5. An authorised publisher can publish the post and copy its public link.

The ZIP must contain one `extraordinary-careers-studio` folder with the main plugin PHP file directly inside it. No build tools, external API keys, or paid services are required on the WordPress server.

## Staff workflow

- Enter one responsibility or requirement per line. The plugin formats these as lists.
- Drafts can be incomplete; publication requires the core role and application fields.
- Review the current preview before publishing. Editing the form invalidates the reviewed preview.
- Save changes to an existing published role without creating another job URL.
- Copy links only after publication. A saved draft is not a public vacancy.
- Choose an image from the Media Library and provide descriptive alt text. The image appears in the preview and is also set as the WordPress featured image.
- Enable online applications per role only after the private application service has been configured.

## WordPress integration

Jobs remain normal WordPress posts, so they can appear in an existing posts-based careers listing. The plugin manages only posts that it creates. Existing posts remain available through the normal WordPress editor; this release does not automatically convert their content into structured fields.

The design uses the portal's observed teal (`#38a3a5`) and dark green (`#081d1e`) palette. The preview shows the plugin's job content, not the complete active theme header and footer. Check the final page on staging with the site's theme and other plugins before production installation.

WordPress capabilities control access. Users who can edit posts can use the studio; publishing additionally requires `publish_posts`. Per-post checks still apply. No new staff roles or accounts are created. Private applications require the separate `manage_career_applications` capability. Administrators receive it on activation and can grant it to authorised HR users through **Users > Edit > Careers Studio HR access**. Job publishing rights alone do not grant CV access.

## HR applications

**HR Applications** provides applicant name/email search, role/status/date filters, review status changes, protected CV downloads, explicit deletion and a guarded cleanup action for expired records. Counters show stored website applications, new applications and approximate HR email-link clicks. Refresh updates the dashboard; there is no polling service. Email clicks do not prove that messages were sent or received.

Application metadata stays in dedicated tables within the existing WordPress database. PDF files remain outside the public web root and never enter the Media Library. CV access expires twelve months after submission. HR must use the manual expiry-cleanup control to remove expired records and files; no automatic deletion schedule is installed.

See [CV-MODULE-PROTOCOL.md](CV-MODULE-PROTOCOL.md) for ownership, submission, retrieval, retention and recovery procedures. See [PRIVATE-STORAGE-SETUP.md](PRIVATE-STORAGE-SETUP.md) for the required hosting setup. Activation alone does not enable public uploads.

## Scope and limits

- No email delivery service, mailbox ingestion, AI profiling or paid service connection.
- Email applications use a `mailto:` link. Online applications accept one PDF CV up to 5 MB and return a reference after successful storage.
- Closing dates use the WordPress site's timezone. Closed-state rendering requires the plugin to remain active, and page caches must expire or be purged after closing dates.
- Deactivation keeps posts and their saved HTML. It removes plugin styling and dynamic closed-state behaviour.
- No automatic deletion, migration of old vacancies or live deployment. Activation adds application/audit tables and the administrator HR capability to the existing WordPress installation.
- The supplied HR documents are not distributed with this plugin.

## Developer notes

The authenticated REST namespace is `extraordinary-careers/v1`. Requests require a WordPress login, REST nonce, and appropriate capabilities. Endpoints list managed jobs, read a single job, save draft/published jobs, and render a preview. Fields are plain text; generated markup is escaped server-side. Existing records require an opaque `modified` value from the latest response to detect stale edits.

Security implementation follows the WordPress [custom endpoint permissions guidance](https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/).

See `TESTING.md` for the verified environment and release checks. Version 0.2.0 is a staging pilot; local checks do not establish production compatibility.
