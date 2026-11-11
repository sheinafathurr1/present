@if (session('success'))
    <div class="alert alert--success" role="status">
        <x-icon name="check" /> <div>{{ session('success') }}</div>
    </div>
@endif
@if (session('error'))
    <div class="alert alert--error" role="alert">
        <x-icon name="alert" /> <div>{{ session('error') }}</div>
    </div>
@endif
@if ($errors->any())
    <div class="alert alert--error" role="alert" tabindex="-1" data-autofocus>
        <x-icon name="alert" />
        <div>
            <strong>Ada {{ $errors->count() }} isian yang perlu diperbaiki:</strong>
            <ul>
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
@if (session('import_errors'))
    <div class="alert alert--warning" role="status">
        <x-icon name="info" />
        <div>
            <strong>Beberapa baris dilewati atau disesuaikan:</strong>
            <ul>
                @foreach (session('import_errors') as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
