@extends('layouts.app')

@section('title', 'Impor Selesai')

@section('content')
@php $withPassword = array_values(array_filter($accounts, fn ($a) => $a['password'] !== null)); @endphp
<div class="page-head no-print">
    <div>
        <h1>Impor selesai</h1>
        <p class="lead">{{ count($created) }} akun baru dibuat · {{ count($updated) }} akun diperbarui</p>
    </div>
    <div class="actions">
        <a href="{{ route('employees.index') }}" class="btn btn--secondary">Selesai</a>
    </div>
</div>

@if ($withPassword !== [])
    <div class="alert alert--warning no-print" role="status">
        <x-icon name="alert" />
        <div><strong>Catat atau cetak kata sandi sekarang.</strong> Kata sandi hanya ditampilkan sekali di halaman ini.
            Minta setiap orang menggantinya lewat menu Akun setelah masuk pertama kali.</div>
    </div>

    <section class="card no-print" aria-labelledby="daftar-akun">
        <div class="card__head">
            <h2 id="daftar-akun" class="card__title">Username & kata sandi</h2>
            <div class="actions">
                <button type="button" class="btn btn--secondary btn--sm" data-print><x-icon name="printer" :size="16" /> Cetak kartu akun</button>
                <button type="button" class="btn btn--secondary btn--sm" data-download-table="#tabel-akun" data-filename="akun-guru-staf.csv"><x-icon name="download" :size="16" /> Unduh (CSV)</button>
            </div>
        </div>
        <div class="table-wrap">
            <table class="table table--stack" id="tabel-akun">
                <thead><tr><th scope="col">Nama</th><th scope="col">Jabatan</th><th scope="col">Username</th><th scope="col">Kata sandi</th><th scope="col">Keterangan</th></tr></thead>
                <tbody>
                @foreach ($withPassword as $account)
                    <tr>
                        <th scope="row">{{ $account['name'] }}</th>
                        <td data-label="Jabatan">{{ $account['position'] ?: $account['type'] }}</td>
                        <td data-label="Username"><code>{{ $account['username'] }}</code></td>
                        <td data-label="Kata sandi"><code>{{ $account['password'] }}</code></td>
                        <td data-label="Keterangan">{{ $account['action'] === 'baru' ? 'Akun baru' : 'Kata sandi diganti' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>

    {{-- Kartu akun untuk digunting dan dibagikan. --}}
    <div class="account-slips print-only">
        @foreach ($withPassword as $account)
            <div class="slip">
                <p class="slip__school">{{ $schoolSettings->schoolName() }} · Presensi guru & staf</p>
                <p class="slip__name">{{ $account['name'] }}</p>
                <dl>
                    <div><dt>Alamat</dt><dd>{{ config('app.url') }}</dd></div>
                    <div><dt>Username</dt><dd>{{ $account['username'] }}</dd></div>
                    <div><dt>Kata sandi</dt><dd>{{ $account['password'] }}</dd></div>
                </dl>
                <p class="slip__note">Ganti kata sandi di menu Akun setelah masuk pertama kali.</p>
            </div>
        @endforeach
    </div>
@endif

@php $updatedOnly = array_values(array_filter($updated, fn ($a) => $a['password'] === null)); @endphp
@if ($updatedOnly !== [])
    <section class="card no-print" aria-labelledby="diperbarui">
        <h2 id="diperbarui" class="card__title">Diperbarui (kata sandi tidak berubah)</h2>
        <ul class="plain-list">
            @foreach ($updatedOnly as $account)
                <li><strong>{{ $account['name'] }}</strong> <code>{{ $account['username'] }}</code></li>
            @endforeach
        </ul>
    </section>
@endif
@endsection
