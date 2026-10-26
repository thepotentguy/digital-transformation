# Careers Studio: proposed application storage protocol

Status: local extension authorised, 26 October 2026. Owner selected twelve months after application as the retention period. No production applicant collection is enabled by this document; implementation and production acceptance remain separate.

## Ownership and storage

HR owns applicant records. The existing website's WordPress database holds application metadata in plugin-specific tables. Do not create a separate database or send CVs into shared Genesis memory, GitHub, usage logs, or public WordPress posts.

CV file bytes belong in a configured private directory outside the public web root. Do not use ordinary Media Library attachment URLs. If private storage cannot be verified on the hosting environment, uploads remain disabled. Downloads must pass through an authenticated endpoint with a dedicated HR capability, per-record checks, no-cache headers and an attachment response. Random filenames supplement access control; they do not replace it.

## Job images and contact actions

Job images are public marketing assets and may use the WordPress Media Library, with descriptive alt text and a preview. CV files use a separate private path.

Show a contact link to HR@extraordinary.co.za. Record aggregate contact-link clicks separately from submitted applications. A click is not evidence that an email was sent, received or read. No mailbox ingestion is included. Counters remain in the HR dashboard and update after actual actions or a manual refresh.

## Submission protocol

1. Verify that the selected job exists, is published, accepts website applications and has not closed, using the WordPress timezone.
2. Show the purpose of collection, required fields, the applicable privacy notice and HR contact. Optional future-vacancy retention must be separately explained and selected.
3. Accept only the necessary applicant details and one bounded CV upload. Initial proposed format: PDF, maximum 5 MB. Do not request identity documents or bank details.
4. Apply server-side size/type/signature checks, request limits and spam controls. File validation does not establish absence of malware. Define the hosting scan/quarantine path before production activation.
5. Assign an opaque application reference and deduplicate repeat submission attempts. Store file and metadata consistently; failed saves must not report success or leave accessible orphan files.
6. Return a receipt only after persistence succeeds. Record the job reference, submission time, consent/notice version and retention state.

## HR retrieval protocol

Only explicitly authorised HR users and administrators with the dedicated capability can search or retrieve applications. Permission to publish jobs does not grant access to CVs.

Search by applicant name/email, job, submission date and review status. Provide paginated results and a private download action. Initial recall is structured search, not AI profiling or automatic applicant ranking. Allow review statuses such as New, Reviewing, Shortlisted and Closed. Audit the actor, action, record reference and time without copying CV content into audit logs.

## Retention, deletion and recovery

Retain CVs for twelve calendar months after application, as selected by the owner. Expired CVs must no longer be downloadable. Provide a guarded HR action to remove expired records and files. No scheduled deletion job is installed by this local build. HR must approve the collection notice, access membership and backup treatment before public activation; the deployment procedure must assign responsibility for timely expiry cleanup.

Provide an authorised deletion workflow covering both the file and metadata, with a minimal audit event. Backups may retain deleted records until their retention expires; document restore procedures that reapply deletions. Define handling for applicant access/correction/deletion requests and security incidents through HR's existing process.

## Release gates

Use synthetic applicant data only for local tests. Verify unauthorised listing/download denial, guessed-reference denial, upload rejection, expired-job rejection, duplicate handling, persistence failure recovery, status changes, deletion and storage isolation. Confirm actual host configuration, capacity, backups, malware scanning and privacy notice on staging. No live applicants, notification emails, mailbox connection, scheduled deletion or production installation are enabled merely by approving a local build.
