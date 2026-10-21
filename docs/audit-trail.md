# Audit Trail

`RecordAuditTrail` wraps web mutations in a database transaction. `AuditTrailRecorder`
observes the domain models listed in `MODELS` and records their final before/after
state. Failed requests and failed audit writes roll back the database operation.
Request throttling executes before this transaction; login uses its own required
audit write so failed-attempt counters remain durable.

Existing `AuditLogger` descriptions remain the visible action entries. Their new
events receive the collected changes in `metadata`, without duplicate generic
entries. If a mutation has no explicit event, the recorder creates an entry.
Student imports have one searchable `ENROLL` event per student. Actor identity is
snapshotted so later renames or deletions do not change the displayed actor.

Actor identity and roles are copied at the start of the operation, before the
account can be renamed or its roles changed. Each newly created account requires
its own `Users / CREATE` event with a saved account reference, creator ID, and
creator role. A batch summary or an event for another account does not satisfy
that requirement. Profile-only creation/removal is an account update.

Both `AuditLogger::log()` and `logOrFail()` propagate failures. Non-HTTP callers
must keep the business operation and its required audit writes in the same
transaction; calling the logger after a commit cannot undo that commit.
The server stamps new events with `metadata.event_source = application`.
Request User-Agent text cannot turn these new events into migration placeholders.
Legacy placeholders retain their existing interpretation and stored values.

Passwords, remember/join tokens, and profile image contents are excluded. Password
and image changes store boolean indicators instead. Profile image cleanup and
current-session password markers run only after the transaction commits.
Account credentials emails, including student imports, are sent only after the
account and audit transaction commits. Mail delivery warnings are added to the
same JSON response or redirect session; SMTP failures keep the committed account.

## Adding actions

- Add new domain model classes to `AuditTrailRecorder::MODELS`.
- Prefer an explicit `AuditLogger::logOrFail()` call for a specific business
  description, passing the affected model. Pass the actual submission or roster
  record when an action is per student.
- Query-builder bulk updates/deletes and database cascades do not fire model
  events. These paths need explicit audit entries and supplied before/after data,
  as in notification read-all, semester activation, and roster removal.
- Designation handlers call `rememberDesignations()` before bulk role/history
  changes so both revoked and newly assigned roles are included in the event.
- Add a route-specific action/description in `describe()` when generic CRUD
  wording would lose the business meaning.
- Avoid writes in GET handlers. Existing assessment start/take and report build
  routes are explicitly included until those flows can be separately refactored.
- CLI, migrations, seeders, and direct SQL are outside the HTTP recorder. Required
  non-HTTP actions must call `logOrFail()` inside their own transaction.

## Historical data

Existing audit rows and timestamps are preserved. Recovery migrations add source
references in metadata. The instructor actor correction only enriches synthetic
enrollment events that have no actor; it does not overwrite original events.
Administrator-confirmed account creations use ordinary creation wording in the
audit UI; their stored description and correction metadata remain unchanged.
Backfills cannot reconstruct edits, deletions, or dates absent from the remaining
records/history. Do not rerun old backfill migrations to manufacture that history.

Run `php artisan migrate` when deploying. The earlier metadata migration and the
enrollment actor correction must be applied before serving the updated audit UI.
The Docker startup already runs `php artisan migrate --force`. Include the new
middleware, recorder, and recovery migration together with their registration
changes in the deployment commit.
