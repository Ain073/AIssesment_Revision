@extends('layouts.portal')

@section('title', 'Accepted Answers | AIssessment Instructor')
@section('header', 'Accepted Answers')

@section('content')
    <div class="mx-auto" style="max-width: 960px;">
        <a class="small fw-semibold text-decoration-none" href="{{ route('instructor.assessments.show', $assessment) }}">Back to Published Assessment</a>
        <h1 class="brand-text mt-2 mb-1" style="color: var(--psu-navy);">Add Accepted Alternative</h1>
        <p class="text-secondary mb-4">{{ $assessment->title }} &middot; Question {{ $item->sort_order }}</p>

        @if (session('status'))
            <div class="alert alert-success" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
        @endif

        <section class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <h2 class="h5 mb-3">{{ $item->question_text }}</h2>
                <p class="small fw-bold text-secondary text-uppercase mb-2">Currently Accepted Answers</p>
                <ul class="mb-3">
                    @foreach ($item->choices->where('is_correct', true) as $choice)
                        <li>{{ $choice->choice_text }}</li>
                    @endforeach
                </ul>
                <p class="text-secondary mb-0">Adding an alternative updates scoring for all existing and future attempts using this published question. Student answers are preserved.</p>
                <div class="d-flex flex-wrap gap-2 mt-3">
                    @foreach ($publications as $publication)
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('instructor.assessments.results', $publication) }}">Results: {{ $publication->class?->displayName() ?? 'Published class' }}</a>
                    @endforeach
                </div>
            </div>
        </section>

        <form action="{{ route('instructor.assessments.items.accepted-answers.preview', [$assessment, $item]) }}" method="POST" class="card border-0 shadow-sm mb-4">
            @csrf
            <div class="card-body p-4">
                <label class="form-label fw-semibold" for="alternativeAnswer">New Alternative Answer</label>
                <input class="form-control" id="alternativeAnswer" name="alternative_answer" type="text" maxlength="1000" value="{{ $preview['alternative'] ?? old('alternative_answer') }}" required aria-describedby="alternativeHelp">
                <p class="form-text" id="alternativeHelp">Enter one accepted spelling, singular/plural form, alias, or acronym. Capitalization and spaces at the beginning or end are ignored. Up to 10 accepted answers per question.</p>
                <button class="btn btn-psu mt-2" type="submit">Preview Score Changes</button>
            </div>
        </form>

        @if ($preview)
            <section class="card border-0 shadow-sm mb-4" aria-labelledby="scorePreviewHeading">
                <div class="card-body p-4">
                    <h2 class="h5" id="scorePreviewHeading">Score Change Preview</h2>
                    <p>New accepted answer: <strong>{{ $preview['alternative'] }}</strong></p>
                    <p><strong>{{ $preview['students_count'] }} student(s)</strong> across <strong>{{ $preview['attempts_count'] }} submitted attempt(s)</strong> will gain credit.</p>
                    @if ($preview['changes']->isNotEmpty())
                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead><tr><th scope="col">Student</th><th scope="col">Attempt</th><th scope="col">Answer</th><th scope="col">Question Score</th></tr></thead>
                                <tbody>
                                    @foreach ($preview['changes']->take(50) as $change)
                                        <tr>
                                            <td>{{ $change['student_name'] }}<span class="d-block small text-secondary">{{ $change['student_number'] }}</span></td>
                                            <td>{{ $change['attempt_number'] }}</td>
                                            <td>{{ $change['answer_text'] }}</td>
                                            <td>{{ $change['before'] }} / {{ (float) $item->points }} &rarr; {{ $change['after'] }} / {{ (float) $item->points }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if ($preview['attempts_count'] > 50)
                            <p class="small text-secondary">Showing the first 50 affected attempts. All {{ $preview['attempts_count'] }} affected attempts will receive credit.</p>
                        @endif
                    @else
                        <p class="text-secondary">No submitted attempts gain credit yet. The alternative will still be accepted for future submissions.</p>
                    @endif
                    <p class="small text-secondary">Results and calculated report statistics use the updated answer key when reopened. Review any previously saved report explanations or AI drafts after correcting scores.</p>
                    <form action="{{ route('instructor.assessments.items.accepted-answers.store', [$assessment, $item]) }}" method="POST">
                        @csrf
                        <input type="hidden" name="preview_token" value="{{ $previewToken }}">
                        <button class="btn btn-psu" type="submit">Save Alternative and Apply to All Attempts</button>
                        <a class="btn btn-outline-secondary ms-2" href="{{ route('instructor.assessments.items.accepted-answers.edit', [$assessment, $item]) }}">Cancel</a>
                    </form>
                </div>
            </section>
        @endif
    </div>
@endsection
