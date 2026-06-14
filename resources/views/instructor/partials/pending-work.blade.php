<div class="task-list">
    @foreach ($pendingWork as $task)
        <article class="task-card">
            <div>
                <p class="fw-bold mb-1" style="color: var(--psu-navy);">{{ $task['title'] }}</p>
                <p class="text-secondary mb-0">{{ $task['description'] }}</p>
            </div>
            @if (! empty($task['href']))
                <a class="status-pill text-decoration-none" href="{{ $task['href'] }}">{{ $task['state'] }}</a>
            @else
                <span class="status-pill">{{ $task['state'] }}</span>
            @endif
        </article>
    @endforeach
</div>
