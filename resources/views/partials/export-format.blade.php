<fieldset class="choice-group">
    <legend>Format</legend>
    <div class="choice-grid">
        <label class="choice">
            <input type="radio" name="format" value="xlsx" @checked(old('format', 'xlsx') === 'xlsx')>
            <span><strong>Excel (.xlsx)</strong><span class="muted">Ringkasan, rincian per hari, dan daftar izin dalam beberapa lembar.</span></span>
        </label>
        <label class="choice">
            <input type="radio" name="format" value="csv" @checked(old('format') === 'csv')>
            <span><strong>CSV</strong><span class="muted">Satu tabel sederhana, bisa dibuka di aplikasi apa pun.</span></span>
        </label>
    </div>
</fieldset>
