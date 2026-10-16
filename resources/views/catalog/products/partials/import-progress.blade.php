{{-- Full-screen progress for the parts import. $steps lists the stage labels shown under the bar. --}}
<div class="import-progress" data-import-progress hidden>
    <div class="import-progress-card" role="dialog" aria-modal="true" aria-labelledby="import-progress-title">
        <strong id="import-progress-title" data-import-progress-title>{{ $title }}</strong>

        <div
            class="import-progress-bar"
            role="progressbar"
            aria-labelledby="import-progress-title"
            aria-valuemin="0"
            aria-valuemax="100"
            aria-valuenow="0"
            data-import-progress-bar
        >
            <span data-import-progress-fill></span>
        </div>

        <div class="import-progress-meta">
            <span data-import-progress-stage aria-live="polite">{{ $steps[0] }}</span>
            <b data-import-progress-percent>0%</b>
        </div>

        <ol class="import-progress-steps">
            @foreach ($steps as $step)
                <li data-import-progress-step>{{ $step }}</li>
            @endforeach
        </ol>

        <div class="import-progress-error" data-import-progress-error hidden role="alert">
            <ul data-import-progress-error-messages></ul>
            <button class="btn-secondary" type="button" data-import-progress-close>Close</button>
        </div>

        <small class="import-progress-note" data-import-progress-note>Please keep this page open until it finishes.</small>
    </div>
</div>
