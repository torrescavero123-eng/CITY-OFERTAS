@php
    $pre = 'background:#f3f4f6;color:#111827;border-radius:6px;padding:10px;font-size:12px;overflow:auto;white-space:pre-wrap;word-break:break-word;';
    $json = static fn ($v): string => $v === null ? '—' : json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
@endphp

<div style="display:grid;gap:14px;">
    <div style="font-size:13px;">
        <div><strong>Quién:</strong> {{ $log->actor?->name ?? 'Sistema' }}</div>
        <div><strong>Cuándo:</strong> {{ $log->created_at?->timezone('America/Lima')->format('d/m/Y H:i:s') }}</div>
        <div><strong>Sobre:</strong> {{ $log->entity_type }}{{ $log->entity_id !== null ? ' #'.$log->entity_id : '' }}</div>
        <div><strong>Código de la acción:</strong> {{ $log->action }}</div>
        @if ($log->ip)<div><strong>IP:</strong> {{ $log->ip }}</div>@endif
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div><strong style="font-size:13px;">Antes</strong><pre style="{{ $pre }}">{{ $json($log->old_values) }}</pre></div>
        <div><strong style="font-size:13px;">Después</strong><pre style="{{ $pre }}">{{ $json($log->new_values) }}</pre></div>
    </div>
</div>
