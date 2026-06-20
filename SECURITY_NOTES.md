# Security Notes

These are the baseline security rules currently applied in the project and should be preserved for future features.

## Applied now

- Protect all `/super-admin/*` routes with the `super_admin` middleware.
- Protect all `/department-chair/*` routes with the `department_chair` middleware.
- Allow only active `super_admin` accounts to access super-admin pages.
- Throttle login attempts to reduce brute-force attacks.
- Hash passwords with Laravel's hashing system. Never store plain-text passwords.
- Default password hashing now uses `argon2id`.
- `HASH_VERIFY=false` is enabled for backward compatibility with older bcrypt hashes; existing passwords can still be checked and then rehashed on login via Laravel's `rehash_on_login` behavior.
- Validate all create and update requests on the server.
- Keep role and ownership checks on the server for instructor classes, class join requests, and assessment management.
- Keep Admin/Dean user, department, and program management limited to the assigned college. If no college scope exists, do not expose all records as a fallback.
- Keep Department Chair user management limited to the assigned department and current program scope.
- Allow instructors to publish only their own assessments to their own classes under the same subject.
- Allow students to see and open only published assessments for classes where they are enrolled.
- Regenerate the session after successful login.
- Invalidate the session and regenerate the CSRF token on logout.
- Use Laravel's password reset broker for forgot-password links.
- Return a generic forgot-password response so email addresses cannot be enumerated.
- Send reset links only for active accounts; inactive accounts must contact an administrator.
- Keep password reset tokens short-lived according to `config/auth.php`.
- Log sensitive actions such as:
  - login success and failure
  - logout
  - password reset link request
  - password reset success or blocked reset
  - college creation
  - department creation
  - user create, update, delete
  - authorization grant and revoke
  - assessment publishing
  - student opening a published assessment

## Rules for future features

- Use middleware for any protected area or role-specific route.
- Keep authorization checks on the server, not only in the UI.
- Use `Hash::make()` and `Hash::check()` for passwords.
- Never expose or edit user passwords from admin management screens.
- Validate every form and request, including hidden inputs.
- Use flash errors and old input for failed validation.
- Add logging for destructive or security-sensitive actions.
- If a feature uses jQuery or AJAX `POST`, `PUT`, or `DELETE`, include the Laravel CSRF token in the request headers.
- Use database transactions when one action writes multiple related records, such as assessment items with choices or publishing one assessment to many classes.
- If sensitive data must be stored and later read back, use Laravel encryption with `Crypt`, not plain text.
- Never store `APP_KEY` in code or version control.
