<?php

namespace App\Http\Controllers;

use App\Enums\LeaveType;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\AttendanceService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Pengajuan izin, sakit, cuti, dan dinas luar dari HP.
 * Langsung tercatat, tanpa persetujuan.
 */
class LeaveRequestController extends Controller
{
    public function index(Request $request): View
    {
        return view('leaves.index', [
            'leaves' => $request->user()->leaveRequests()->withCount('attendances')->orderByDesc('start_date')->paginate(15),
            'today' => CarbonImmutable::today(),
        ]);
    }

    public function create(Request $request): View
    {
        $today = CarbonImmutable::today();

        return view('leaves.create', [
            'types' => LeaveType::cases(),
            'minDate' => $today->subDays(config('presensi.leave.backdate_days'))->toDateString(),
            'maxDate' => $today->addDays(config('presensi.leave.advance_days'))->toDateString(),
            'maxKb' => config('presensi.leave.attachment_max_kb'),
            'today' => $today->toDateString(),
        ]);
    }

    public function store(Request $request, AttendanceService $service): RedirectResponse
    {
        $today = CarbonImmutable::today();
        $min = $today->subDays(config('presensi.leave.backdate_days'));
        $max = $today->addDays(config('presensi.leave.advance_days'));

        $data = $request->validate([
            'type' => ['required', Rule::enum(LeaveType::class)],
            'start_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$min->toDateString(), 'before_or_equal:'.$max->toDateString()],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date', 'before_or_equal:'.$max->toDateString()],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,heic,pdf', 'max:'.config('presensi.leave.attachment_max_kb')],
        ], [
            'start_date.after_or_equal' => 'Pengajuan paling lama '.config('presensi.leave.backdate_days').' hari ke belakang (mulai '.$min->translatedFormat('d F Y').'). Untuk tanggal yang lebih lama, hubungi admin.',
            'start_date.before_or_equal' => 'Pengajuan paling jauh sampai '.$max->translatedFormat('d F Y').'.',
            'end_date.before_or_equal' => 'Pengajuan paling jauh sampai '.$max->translatedFormat('d F Y').'.',
            'end_date.after_or_equal' => 'Tanggal akhir tidak boleh sebelum tanggal mulai.',
            'attachment.max' => 'Lampiran paling besar '.round(config('presensi.leave.attachment_max_kb') / 1024).' MB.',
            'attachment.uploaded' => 'Lampiran gagal diunggah. Coba foto ulang dengan ukuran lebih kecil.',
        ]);

        $user = $request->user();
        $start = CarbonImmutable::parse($data['start_date']);
        $end = CarbonImmutable::parse($data['end_date'] ?? $data['start_date']);

        if ($start->diffInDays($end) + 1 > config('presensi.leave.max_days')) {
            throw ValidationException::withMessages(['end_date' => 'Satu pengajuan paling lama '.config('presensi.leave.max_days').' hari.']);
        }

        $overlap = $user->leaveRequests()->overlapping($start->toDateString(), $end->toDateString())->first();
        if ($overlap) {
            throw ValidationException::withMessages(['start_date' => "Sudah ada pengajuan {$overlap->type->label()} pada {$overlap->periodLabel()}. Batalkan dulu bila ingin mengubahnya."]);
        }

        $schoolDays = $service->schoolDaysBetween($start, $end);
        if ($schoolDays === []) {
            throw ValidationException::withMessages(['start_date' => 'Tidak ada hari sekolah pada tanggal itu (libur atau akhir pekan), jadi tidak perlu mengajukan.']);
        }

        $leave = $user->leaveRequests()->create([
            'type' => $data['type'],
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'reason' => trim($data['reason']),
            'attachment_path' => $request->file('attachment')?->store('lampiran-izin/'.$user->id, 'local'),
        ]);

        $result = $service->recordLeave($leave);

        $message = sprintf('%s %s tercatat untuk %d hari sekolah.', $leave->type->label(), $leave->periodLabel(), count($result['recorded']));
        if ($result['skipped'] !== []) {
            $message .= ' '.collect($result['skipped'])->map(fn (string $date) => CarbonImmutable::parse($date)->translatedFormat('d M'))->join(', ', ' dan ')
                .' tidak diubah karena Anda sudah presensi masuk.';
        }

        return redirect()->route('leaves.index')->with('success', $message);
    }

    public function destroy(Request $request, LeaveRequest $leave, AttendanceService $service): RedirectResponse
    {
        abort_unless($leave->user_id === $request->user()->id, 403);

        if (! $leave->canBeCancelledBy($request->user())) {
            return back()->with('error', 'Pengajuan yang sudah lewat tidak bisa dibatalkan. Hubungi admin bila perlu diperbaiki.');
        }

        $label = $leave->type->label().' '.$leave->periodLabel();
        $result = $service->cancelLeave($leave);

        return redirect()->route('leaves.index')->with('success', $result === 'dihapus'
            ? "{$label} dibatalkan."
            : "{$label} diakhiri. Mulai hari ini Anda bisa presensi seperti biasa; hari sebelumnya tetap tercatat.");
    }

    public function attachment(Request $request, LeaveRequest $leave): StreamedResponse
    {
        abort_unless($leave->canBeViewedBy($request->user()), 403);
        abort_unless($leave->attachment_path && Storage::disk('local')->exists($leave->attachment_path), 404);

        $extension = pathinfo($leave->attachment_path, PATHINFO_EXTENSION);
        $name = sprintf('%s-%s-%s.%s', $leave->type->value, str($leave->user->name)->slug(), $leave->start_date, $extension);

        return Storage::disk('local')->response($leave->attachment_path, $name, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Semua pengajuan guru & staf (pimpinan & admin). */
    public function monitor(Request $request): View
    {
        $month = HistoryController::month($request);
        $type = LeaveType::tryFrom((string) $request->query('jenis'));

        $leaves = LeaveRequest::with('user')
            ->overlapping($month->startOfMonth()->toDateString(), $month->endOfMonth()->toDateString())
            ->when($type, fn ($query) => $query->where('type', $type))
            ->when($request->integer('pegawai'), fn ($query, $id) => $query->where('user_id', $id))
            ->orderByDesc('start_date')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        return view('leaves.monitor', [
            'leaves' => $leaves,
            'month' => $month,
            'type' => $type,
            'types' => LeaveType::cases(),
            'employees' => User::attending()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
