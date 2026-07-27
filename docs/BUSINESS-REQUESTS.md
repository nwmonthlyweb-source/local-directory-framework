# Business Requests

## Release

Implemented in Local Directory Framework 0.1.14.

## Purpose

Provide a public Manage a Business workflow without requiring visitor accounts.

## Public Request Types

- Add a Business
- Claim or Update a Business
- Request a Correction
- Request Removal

## Public Route

`/manage-a-business/`

The business archive links to the general request page. Individual business
profiles link to the same page with that business preselected.

## Verification Workflow

1. The visitor submits a nonce-protected request form.
2. The request is saved with `pending_email` status.
3. A cryptographically random verification token is emailed to the requester.
4. Only the SHA-256 token hash is stored in WordPress.
5. The verification link expires after 7 days.
6. Successful verification changes the status to `pending_review`.
7. The administrator receives a review notification.

If the verification email cannot be sent, the new request record is deleted.

## Request Safety

- No public request changes or publishes a Business profile automatically.
- Every request requires administrator review.
- Public input is sanitized and length-limited.
- Website URLs must use public HTTP or HTTPS URLs.
- A honeypot field reduces basic automated submissions.
- Submission rate limiting uses a salted hash of email and remote address.
- Raw IP addresses are not stored by the request workflow.
- Unverified requests cannot be approved or completed.
- Requests are archived rather than permanently deleted through the admin UI.

## Administrator Workflow

Businesses → Business Requests

Administrators can:

- Filter requests by status.
- Review submitted details.
- Open the related Business profile.
- Record internal notes.
- Set Pending Review, Approved, Rejected, Completed, or Archived status.

Administrator notes are private and are not shown to the requester.

## Email Requirements

The WordPress site must be able to send email through `wp_mail()`.

Production sites should configure a reliable transactional email or SMTP
provider and test delivery before launch.
