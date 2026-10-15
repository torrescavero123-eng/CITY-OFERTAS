@php
    $fmt = static fn (?string $n): string => $n === null ? '—' : (rtrim(rtrim(number_format((float) $n, 3, '.', ','), '0'), '.') ?: '0');
    $th = 'text-align:left;padding:6px 10px;border-bottom:2px solid #d1d5db;font-size:12px;white-space:nowrap;';
    $thr = 'text-align:right;padding:6px 10px;border-bottom:2px solid #d1d5db;font-size:12px;white-space:nowrap;';
    $td = 'padding:6px 10px;border-bottom:1px solid #e5e7eb;font-size:13px;';
    $tdr = $td.'text-align:right;font-variant-numeric:tabular-nums;';
@endphp

<div>
    @if ($showExpected)
        <p style="font-size:13px;opacity:.75;margin-bottom:8px;">Como supervisor ve el stock esperado. Quien cuenta no lo ve, para no sesgar el conteo.</p>
    @endif
    @if (count($lines) === 0)
        <p style="font-size:13px;opacity:.75;">Este conteo no tiene líneas (no había stock en el alcance elegido).</p>
    @else
        <div style="overflow-x:auto;">
        <table style="border-collapse:collapse;width:100%;">
            <thead>
            <tr>
                <th style="{{ $th }}">Ubicación</th><th style="{{ $th }}">SKU</th><th style="{{ $th }}">Producto</th>
                @if ($showExpected)<th style="{{ $thr }}">Esperado</th>@endif
                <th style="{{ $thr }}">Contado</th>
                @if ($showExpected)<th style="{{ $thr }}">Diferencia</th>@endif
                <th style="{{ $th }}">Estado</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($lines as $l)
                <tr>
                    <td style="{{ $td }}">{{ $l->location_code }}</td>
                    <td style="{{ $td }}">{{ $l->sku }}</td>
                    <td style="{{ $td }}">{{ $l->name }}</td>
                    @if ($showExpected)<td style="{{ $tdr }}">{{ $fmt($l->expected) }}</td>@endif
                    <td style="{{ $tdr }}">{{ $fmt($l->counted) }}</td>
                    @if ($showExpected)
                        <td style="{{ $tdr }}{{ $l->difference !== null && (float) $l->difference !== 0.0 ? 'color:#b45309;font-weight:600;' : '' }}">{{ $fmt($l->difference) }}</td>
                    @endif
                    <td style="{{ $td }}">
                        @if ($l->counted === null) Sin contar
                        @elseif ($l->recount_requested) <strong style="color:#b45309;">Recontar</strong>
                        @else Contada @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
    @endif
</div>
