# Local Directory Framework repository rules

- This repository contains a production WordPress plugin. Work only inside this repository.
- Preserve existing working behavior unless a task explicitly changes it. Prefer small, secure, reusable, and maintainable changes.
- Follow WordPress coding and security practices.
- Require capability checks for privileged operations.
- Require nonces for state-changing admin, form, AJAX, and `admin-post` actions.
- Sanitize and validate incoming values, and escape user-facing output at render time.
- Use prepared SQL for dynamic database queries and safe redirects for navigation.
- Never expose credentials or secrets, and never access or modify `wp-config.php`.
- Never access the live site unless explicitly authorized, and never alter production data during repository checks.
- Never install packages unless explicitly authorized.
- Never commit or push unless explicitly authorized. Never use destructive Git commands.
- After file changes, run `git status --short` and `git diff --check`. Do not commit while `git diff --check` reports errors.
- Review the complete diff before reporting completion.
- When a version bump is explicitly requested, update both the plugin `Version` header and `NWMD_DIRECTORY_VERSION`.
- When a commit is explicitly authorized, the current Git author identity is `Daniel Maksimov <nwmonthlyweb@gmail.com>`.
- Repository: `nwmonthlyweb-source/local-directory-framework`.
- Branch: `feature/operator-restructure`.
