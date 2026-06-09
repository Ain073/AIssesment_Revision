# Security Notes

These are the baseline security rules currently applied in the project and should be preserved for future features.

## Applied now

- Protect all `/super-admin/*` routes with the `super_admin` middleware.
- Allow only active `super_admin` accounts to access super-admin pages.
- Throttle login attempts to reduce brute-force attacks.
- Hash passwords with Laravel's hashing system. Never store plain-text passwords.
- Default password hashing now uses `argon2id`.
- `HASH_VERIFY=false` is enabled for backward compatibility with older bcrypt hashes; existing passwords can still be checked and then rehashed on login via Laravel's `rehash_on_login` behavior.
- Validate all create and update requests on the server.
- Regenerate the session after successful login.
- Invalidate the session and regenerate the CSRF token on logout.
- Log sensitive actions such as:
  - login success and failure
  - logout
  - college creation
  - department creation
  - user create, update, delete
  - authorization grant and revoke

## Rules for future features

- Use middleware for any protected area or role-specific route.
- Keep authorization checks on the server, not only in the UI.
- Use `Hash::make()` and `Hash::check()` for passwords.
- Never expose or edit user passwords from admin management screens.
- Validate every form and request, including hidden inputs.
- Use flash errors and old input for failed validation.
- Add logging for destructive or security-sensitive actions.
- If a feature uses jQuery or AJAX `POST`, `PUT`, or `DELETE`, include the Laravel CSRF token in the request headers.
- If sensitive data must be stored and later read back, use Laravel encryption with `Crypt`, not plain text.
- Never store `APP_KEY` in code or version control.
