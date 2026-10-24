# Public record URLs

Record paths use server-generated UUIDs from `public_id`. Numeric primary and
foreign keys continue to identify database relationships. Public IDs do not
replace authentication, portal roles, department scope, enrollment, or ownership
checks. They are identifiers, not encrypted secrets or access tokens.

The remaining route models are `Subject`, `ClassDetail` (join requests),
`AssessmentItem`, and `Submission`. Their mutation routes accept UUIDs only;
numeric or malformed identifiers are rejected. Existing forms opened before this
deployment must be refreshed before saving.

The only legacy numeric record path is a GET grading link. It looks up the
submission within the authenticated instructor's assessments, then redirects
to the UUID grading URL with `Cache-Control: private, no-store`. Unknown and
other instructors' numeric links return 404. Numeric PUT/DELETE/POST requests
have no compatibility fallback (the GET-only legacy grading path returns 405
for unsupported methods). Both UUID grading reads and writes authorize
ownership before loading student answers.

Pagination, year levels, and filter values are not authentication credentials.
Internal form keys and database foreign keys are unchanged. Class join links
still use their existing random invitation tokens and require authenticated
student access and teacher approval.

## Deployment

Deploy the model, controller, view, route, service, and migration changes together.
Run `php artisan migrate --force` before serving the new code. The existing Docker
startup already does this. The new migration adds nullable unique UUID fields and
backfills only missing/empty public IDs in bounded transactions. It does not
update business fields, primary keys, foreign keys, timestamps, or audit history.
Existing nonempty public IDs are retained; rollback does not remove them.

Drain old application instances/workers that can still create records before
considering cutover complete. If old code wrote records during deployment, run
`php artisan public-ids:backfill` after those instances stop. This idempotent
command assigns only missing public IDs, without changing timestamps or firing
model/audit events. Do not rerun historical migrations or reset the database.

Review the diff and take the normal database backup before applying migrations.
Local SQLite tests verify identifier preservation, routing, and access boundaries;
they do not verify the production TiDB migration or Render rollout.

References: [OWASP IDOR prevention](https://cheatsheetseries.owasp.org/cheatsheets/Insecure_Direct_Object_Reference_Prevention_Cheat_Sheet.html),
[Laravel route model binding](https://laravel.com/docs/12.x/routing#route-model-binding),
[UUID security considerations](https://www.rfc-editor.org/rfc/rfc9562.html#section-8).
