{{-- Jumlah per status dalam sebulan untuk satu orang. $row dari MonthlyReport. --}}
<dl class="month-stats">
    @foreach (\App\Enums\AttendanceStatus::cases() as $status)
        <div class="month-stats__item month-stats__item--{{ $status->tone() }}">
            <dt><span class="swatch swatch--{{ $status->tone() }}" aria-hidden="true"></span>{{ $status->label() }}</dt>
            <dd>{{ $row['totals'][$status->value] }}</dd>
        </div>
    @endforeach
    <div class="month-stats__item">
        <dt>Total terlambat</dt>
        <dd>{{ $row['late_minutes'] ? \App\Models\Attendance::minutes($row['late_minutes']) : '0' }}</dd>
    </div>
    <div class="month-stats__item">
        <dt>Pulang awal</dt>
        <dd>{{ $row['early_count'] }}<small> kali</small></dd>
    </div>
</dl>
