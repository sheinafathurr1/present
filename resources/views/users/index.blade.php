@extends('layouts.app')

@section('title', 'Pengguna')

@section('content')
<div class="page-head">
    <div>
        <h1>Pengguna</h1>
        <p class="lead">Akun untuk admin dan guru. Setiap guru login di PC-nya sendiri yang tersambung ke pembaca kartu.</p>
    </div>
    <div class="actions"><a href="{{ route('users.create') }}" class="btn btn--primary"><x-icon name="plus" /> Tambah pengguna</a></div>
</div>

<section class="card">
    <div class="table-wrap">
        <table class="table table--stack">
            <caption class="sr-only">Daftar pengguna</caption>
            <thead>
            <tr>
                <th scope="col">Nama</th>
                <th scope="col">Email</th>
                <th scope="col">Peran</th>
                <th scope="col"><span class="sr-only">Aksi</span></th>
            </tr>
            </thead>
            <tbody>
            @foreach ($users as $user)
                <tr>
                    <th scope="row" data-label="Nama">{{ $user->name }} @if ($user->is(auth()->user()))<span class="badge badge--info">Anda</span>@endif</th>
                    <td data-label="Email">{{ $user->email }}</td>
                    <td data-label="Peran">{{ $user->role->label() }}</td>
                    <td data-label="Aksi" class="row-actions">
                        <a href="{{ route('users.edit', $user) }}" class="btn btn--ghost btn--sm"><x-icon name="edit" :size="16" /> Ubah<span class="sr-only"> {{ $user->name }}</span></a>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</section>
@endsection
