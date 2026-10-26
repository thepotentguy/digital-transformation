# Extraordinary Careers Studio

A WordPress plugin that guides staff through creating consistent career posts: role details, requirements, application instructions, preview, and publication.

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

## WordPress integration

Jobs remain normal WordPress posts, so they can appear in an existing posts-based careers listing. The plugin manages only posts that it creates. Existing posts remain available through the normal WordPress editor; this release does not automatically convert their content into structured fields.

The design uses the portal's observed teal (`#38a3a5`) and dark green (`#081d1e`) palette. The preview shows the plugin's job content, not the complete active theme header and footer. Check the final page on staging with the site's theme and other plugins before production installation.

WordPress capabilities control access. Users who can edit posts can use the studio; publishing additionally requires `publish_posts`. Per-post checks still apply. No new staff roles or accounts are created. Administrators should assign existing WordPress roles deliberately.

## Scope and limits

- No applicant database, CV upload, email delivery service, AI calls, or analytics collection.
- Applications use the applicant's email application through a `mailto:` link.
- Closing dates use the WordPress site's timezone. Closed-state rendering requires the plugin to remain active, and page caches must expire or be purged after closing dates.
- Deactivation keeps posts and their saved HTML. It removes plugin styling and dynamic closed-state behaviour.
- No automatic deletion, migration of old vacancies, live deployment, or site configuration changes.
- The supplied HR documents are not distributed with this plugin.

## Developer notes

The authenticated REST namespace is `extraordinary-careers/v1`. Requests require a WordPress login, REST nonce, and appropriate capabilities. Endpoints list managed jobs, read a single job, save draft/published jobs, and render a preview. Fields are plain text; generated markup is escaped server-side. Existing records require an opaque `modified` value from the latest response to detect stale edits.

Security implementation follows the WordPress [custom endpoint permissions guidance](https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/).

See `TESTING.md` for the verified environment and release checks. Version 0.1.0 is a staging pilot; local checks do not establish production compatibility.
