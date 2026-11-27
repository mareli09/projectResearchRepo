{{-- Workflow statuses (CLAUDE.md §15 wording). --}}
@props(['status'])
@php
    $map = ['Draft' => [null, 'Draft'], 'Submitted' => ['info', 'Submitted'], 'Endorsed' => ['gold', 'Endorsed by adviser'], 'Reviewed' => ['gold', 'Reviewed by coordinator'], 'Published' => ['ok', 'Published'], 'Returned' => ['bad', 'Returned'], 'Rejected' => ['bad', 'Rejected']];
    [$variant, $label] = $map[$status] ?? [null, $status];
@endphp
<x-pill :variant="$variant">{{ $label }}</x-pill>
