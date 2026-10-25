# Correcting published Identification answer keys

An instructor can add an accepted alternative from **Check Submission** or the
published assessment's **Saved Questions** section. Choose **Add Accepted
Alternative**, enter one alternative, select **Preview Score Changes**, review
the affected students and question scores, then save.

For example, accepting `Humanistic psychologist` alongside the existing
`Humanistic psychologists` gives matching submitted answers credit immediately
when results are reopened. Students do not need to retake the assessment.

The correction only appends an accepted answer. It preserves the question,
points, existing choices, and student responses. It applies to all attempts
using that published question, including future submissions. Independently
published copies and the original draft keep their own answer keys. Legacy
publications sharing one question are all included in the preview.

The owner and parent assessment are checked on every request. Only published
Identification questions support this action; normal question editing remains
locked. Blank, duplicate, oversized, and excess alternatives are rejected. The
limit is 10 accepted answers including the primary answer. Matching retains the
existing scorer's case-insensitive comparison and trimming of outer whitespace;
singular/plural forms are accepted only when explicitly listed.

Saving requires an encrypted preview bound to the instructor, assessment,
question, alternative, current answer key, and affected submissions. Previews
expire after 20 minutes. Changed keys or affected submissions require a fresh
preview. Saving locks the assessment/question and writes the added answer and
an `ADD_ACCEPTED_ANSWER` audit event in one transaction. The audit records the
accepted answers before/after and affected student/attempt counts. Audit failure
rolls back the correction.

Results, best-attempt selection, report statistics, and item analysis already
calculate scores from the current accepted answers, so no stored student answer
or essay score needs rewriting. Previously saved report explanations, AI drafts,
and downloaded exports are not regenerated; review them after correcting scores.

No database migration is required. Local automated verification uses isolated
in-memory SQLite databases and does not establish deployment behavior on
Render/TiDB.
