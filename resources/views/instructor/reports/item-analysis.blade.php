@push('styles')
    <style>
        #reportItemAnalysisModal .item-analysis-table { min-width: 720px; }
        #reportItemAnalysisModal .item-analysis-question { min-width: 220px; max-width: 360px; overflow-wrap: anywhere; white-space: pre-line; }
        #reportItemAnalysisModal .item-analysis-basis { overflow-wrap: anywhere; }
        #reportItemAnalysisModal .item-analysis-table th { vertical-align: middle; }
        #reportItemAnalysisModal .item-analysis-rate { white-space: nowrap; }
    </style>
@endpush

<div class="modal fade d-print-none" id="reportItemAnalysisModal" tabindex="-1" aria-labelledby="reportItemAnalysisModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="reportItemAnalysisModalLabel">{{ $reportTypeLabel }} Item Analysis</h2>
                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <label class="form-label" for="reportItemAnalysisQuiz">Quiz / Assessment</label>
                <select class="form-select mb-3" id="reportItemAnalysisQuiz">
                    @foreach ($rows as $row)
                        <option value="{{ $loop->index }}">{{ $row['assessment']->title }} - {{ $row['class']?->displayName() ?? 'Class' }}</option>
                    @endforeach
                </select>
                @forelse ($rows as $row)
                    @php
                        $analysis = $row['analytics'];
                        $strongest = collect($analysis['strongest_items']);
                        $weakest = collect($analysis['weakest_items']);
                        $uniform = $strongest->isNotEmpty() && $strongest->pluck('item_number')->all() === $weakest->pluck('item_number')->all();
                        $hasPending = collect($analysis['item_analysis'])->contains(fn ($item) => $item['pending_count'] > 0);
                    @endphp
                    <section data-item-analysis-panel="{{ $loop->index }}" @if (! $loop->first) hidden @endif aria-label="{{ $row['assessment']->title }} item analysis">
                        <div class="d-flex flex-wrap gap-3 mb-2 small">
                            <span><strong>{{ $analysis['takers_count'] }}</strong> takers</span>
                            <span><strong>{{ $analysis['item_count'] }}</strong> items</span>
                            <span>Highest-scoring submitted attempt per student</span>
                        </div>
                        <p class="small text-secondary mb-3">% correct = fully correct answers / takers &times; 100. Incorrect includes partial answers.</p>
                        @if ($analysis['takers_count'] === 0)
                            <div class="alert alert-light border">No completed submissions available.</div>
                        @endif
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm align-middle item-analysis-table">
                                <caption class="visually-hidden">{{ $row['assessment']->title }}: item results and most/least learned basis</caption>
                                <thead class="table-light">
                                    <tr>
                                        <th scope="col">Item</th>
                                        <th scope="col">Question</th>
                                        <th class="text-center" scope="col">Correct</th>
                                        <th class="text-center" scope="col">Incorrect</th>
                                        <th class="text-center" scope="col">Unanswered</th>
                                        @if ($hasPending)<th class="text-center" scope="col">Pending</th>@endif
                                        <th class="text-center" scope="col">% Correct</th>
                                        <th scope="col">Basis</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($analysis['item_analysis'] as $item)
                                        <tr>
                                            <th scope="row">{{ $item['item_number'] }}</th>
                                            <td class="item-analysis-question">{{ $item['question'] }}</td>
                                            <td class="text-center">{{ $item['correct_count'] }}</td>
                                            <td class="text-center">{{ $item['incorrect_count'] }}</td>
                                            <td class="text-center">{{ $item['unanswered_count'] }}</td>
                                            @if ($hasPending)<td class="text-center">{{ $item['pending_count'] }}</td>@endif
                                            <td class="text-center item-analysis-rate">{{ $item['correct_rate'] !== null ? $item['correct_rate'].'%' : ($item['pending_count'] > 0 ? 'Pending' : 'N/A') }}</td>
                                            <td>
                                                @if ($uniform && $item['correct_rate'] !== null)
                                                    <span class="small text-secondary">Equal performance</span>
                                                @else
                                                    @if ($strongest->contains('item_number', $item['item_number']))
                                                        <span class="d-block small fw-semibold text-success">Most learned</span>
                                                    @endif
                                                    @if ($weakest->contains('item_number', $item['item_number']))
                                                        <span class="d-block small fw-semibold text-danger">Least learned</span>
                                                    @endif
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="{{ $hasPending ? 8 : 7 }}" class="text-center text-secondary py-3">No items available.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="item-analysis-basis border-top pt-3 mt-2">
                            @if ($uniform)
                                <p class="mb-2">All graded items have the same correct rate ({{ $strongest->first()['correct_rate'] }}%). There is no difference between the most and least learned rankings.</p>
                            @endif
                            <dl class="mb-0">
                                <dt class="small text-success">Most Learned Basis</dt>
                                <dd>{{ $strongest->isNotEmpty() ? 'Item '.$strongest->pluck('item_number')->implode(', ').' - '.$strongest->first()['correct_rate'].'% correct' : 'Not available' }}</dd>
                                <dt class="small text-danger">Least Learned Basis</dt>
                                <dd>{{ $weakest->isNotEmpty() ? 'Item '.$weakest->pluck('item_number')->implode(', ').' - '.$weakest->first()['correct_rate'].'% correct' : 'Not available' }}</dd>
                            </dl>
                            @if ($hasPending)
                                <p class="small text-secondary mb-0">Items with pending grades are excluded from the most and least learned rankings.</p>
                            @endif
                        </div>
                    </section>
                @empty
                    <p class="text-secondary mb-0">No assessments available.</p>
                @endforelse
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
