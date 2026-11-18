{{--
    Daftar hari dalam sebulan untuk satu orang (riwayat sendiri / detail pegawai).
    $days & $row dari MonthlyReport; $correctFor = User bila admin boleh mengoreksi; $showLocation untuk pimpinan.
--}}
@php
    $correctFor = $correctFor ?? null;
    $showLocation = $showLocation ?? false;
    $visible = array_filter($days, fn ($day) => ! $day['future'] && ($day['school'] || $row['cells'][$day['date']]));
@endphp
@if ($visible === [])
    <p class="empty">Belum ada hari sekolah di bulan ini.</p>
@else
    <div class="table-wrap">
        <table class="table table--stack table--grid table--days">
            <thead>
            <tr>
                <th scope="col">Tanggal</th>
                <th scope="col">Status</th>
                <th scope="col">Masuk</th>
                <th scope="col">Pulang</th>
                <th scope="col">Keterangan</th>
                @if ($correctFor)<th scope="col"><span class="sr-only">Aksi</span></th>@endif
            </tr>
            </thead>
            <tbody>
            @foreach (array_reverse($visible) as $day)
                @php $attendance = $row['cells'][$day['date']]; @endphp
                <tr @if ($day['today']) class="is-today" @endif>
                    <th scope="row">
                        {{ \Carbon\Carbon::parse($day['date'])->translatedFormat('D, d M') }}
                        @if ($day['today'])<span class="badge badge--info">Hari ini</span>@endif
                        @if ($day['holiday'])<span class="muted">{{ $day['holiday'] }}</span>@endif
                    </th>
                    <td data-label="Status">
                        @if ($attendance)
                            <x-status-badge :status="$attendance->status" />
                        @elseif ($day['today'])
                            <x-status-badge />
                        @else
                            <x-status-badge empty="Tidak ada catatan" />
                        @endif
                    </td>
                    <td data-label="Masuk">
                        {{ $attendance?->check_in_at?->format('H:i') ?? '—' }}
                        @if ($showLocation && $attendance?->check_in_distance !== null)
                            <span class="muted">{{ $attendance->check_in_distance }} m · ± {{ $attendance->check_in_accuracy }} m</span>
                        @endif
                    </td>
                    <td data-label="Pulang">
                        {{ $attendance?->check_out_at?->format('H:i') ?? '—' }}
                        @if ($showLocation && $attendance?->check_out_distance !== null)
                            <span class="muted">{{ $attendance->check_out_distance }} m · ± {{ $attendance->check_out_accuracy }} m</span>
                        @endif
                    </td>
                    <td data-label="Keterangan">
                        @if ($attendance)
                            @php $remarks = $attendance->remarks($day['today']); @endphp
                            @if ($remarks)<span>{{ implode(' · ', $remarks) }}</span>@endif
                            @if ($attendance->leaveRequest)
                                <span class="note">{{ $attendance->leaveRequest->reason }}</span>
                            @endif
                            @if ($attendance->source === \App\Models\Attendance::SOURCE_ADMIN)
                                <span class="note">Koreksi admin: {{ $attendance->note }}</span>
                            @elseif ($attendance->source === \App\Models\Attendance::SOURCE_SYSTEM)
                                <span class="note">Tidak presensi & tidak mengajukan izin</span>
                            @endif
                        @endif
                    </td>
                    @if ($correctFor)
                        <td class="row-actions">
                            <a class="btn btn--ghost btn--sm" href="{{ route('corrections.edit', [$correctFor, $day['date']]) }}">
                                <x-icon name="edit" :size="16" /> Koreksi<span class="sr-only"> {{ $day['label'] }}</span>
                            </a>
                        </td>
                    @endif
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif
