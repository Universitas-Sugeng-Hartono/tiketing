@extends('layouts.app')

@section('content')
@php
    $statusColors = [
        'open' => 'bg-amber-50 text-amber-700 border-amber-200',
        'in_progress' => 'bg-sky-50 text-sky-700 border-sky-200',
        'escalated' => 'bg-violet-50 text-violet-700 border-violet-200',
        'waiting_next_approval' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
        'resolved' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'rejected' => 'bg-rose-50 text-rose-700 border-rose-200',
    ];
    $defaultAction = $ticket->workflow_type === 'tiered_approval'
        ? 'approve_step'
        : ($nextSuperiorRole ? 'escalate' : 'resolve');
    $holderUser = \App\Models\User::where('role_id', $ticket->current_role_id)->first();
    $isWorkDesk = ($canDirect ?? false) && $roleStaff->isNotEmpty();
    $approveOnly = (bool) $nextSuperiorRole
        && ! $isWorkDesk
        && ! $user->isSuperuser()
        && ! $ticket->currentRole?->head_user_id;
    $showActionPanel = $canHandle && (
        $user->isSuperuser()
        || ($canDirect ?? false)
        || ! $ticket->currentRole?->head_user_id
    );

    $problemTotal = $ticket->problems->count();
    $problemDone = $ticket->problems->where('is_done', true)->count();
    $problemPercent = $ticket->progressPercent();
    $totalWeightConfigured = (int) $ticket->problems->sum('weight_percent');
    $doneWeightConfigured = (int) $ticket->problems->where('is_done', true)->sum('weight_percent');

    $canEditProblems = $canDirect ?? $ticket->canBeDirectedBy($user);
    $canToggleProblems = $ticket->canUpdateProblemProgress($user);
    $canReportProgress = $ticket->canReportProgress($user);
@endphp

<div x-data="{
    actionModal: {{ ($errors->has('action_type') || $errors->has('note') || old('action_type')) ? 'true' : 'false' }},
    progressModal: {{ ($errors->has('progress_percent') || $errors->has('work_status')) ? 'true' : 'false' }},
    addProblemModal: false,
    revisiModal: {{ $errors->has('note') && old('from_revisi') ? 'true' : 'false' }},
    actionType: '{{ old('action_type', $isWorkDesk ? 'in_progress' : $defaultAction) }}'
}" class="max-w-6xl mx-auto space-y-6 pb-12">

    <!-- Top Navigation & Role Alert -->
    <div class="flex items-center justify-between gap-4 flex-wrap">
        <a href="{{ url()->previous() == url()->current() ? route('tickets.inbox') : url()->previous() }}"
           class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-slate-900 transition">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/>
            </svg>
            Kembali ke Daftar Tiket
        </a>

        @if(!$canHandle && !in_array($ticket->status, ['resolved', 'rejected', 'closed'], true))
            <div class="inline-flex items-center gap-2.5 px-3.5 py-1.5 rounded-full bg-amber-50 border border-amber-200/80 text-xs text-amber-800">
                <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                <span>Posisi tiket saat ini: <strong>Role {{ $ticket->currentRole?->name }}</strong></span>
                @if($holderUser && (app()->environment('local') || config('app.debug')))
                    <form action="{{ route('auth.quick-switch', $holderUser) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="font-bold underline hover:text-amber-950 ml-1">
                            Beralih ke {{ $ticket->currentRole?->name }} →
                        </button>
                    </form>
                @endif
            </div>
        @endif
    </div>

    <!-- 1. KARTU UTAMA TIKET (Clean & Minimalist Hero) -->
    <div class="bg-white rounded-2xl border border-slate-200/90 shadow-xs overflow-hidden">
        <div class="p-6 sm:p-7">
            <!-- Header Badges, Dates, & Action Button -->
            <div class="flex flex-wrap items-center justify-between gap-3 pb-5 border-b border-slate-100">
                <div class="flex flex-wrap items-center gap-2.5">
                    <span class="font-mono text-xs font-bold px-2.5 py-1 rounded-lg bg-indigo-50 text-indigo-700 border border-indigo-100">
                        #{{ $ticket->ticket_number }}
                    </span>
                    <span class="text-xs font-semibold px-2.5 py-1 rounded-lg border {{ $statusColors[$ticket->status] ?? 'bg-slate-100' }}">
                        {{ $ticket->statusLabel() }}
                    </span>
                    <span class="text-xs font-semibold px-2.5 py-1 rounded-lg bg-slate-100 text-slate-700 border border-slate-200">
                        Prioritas: {{ $ticket->priorityLabel() }}
                    </span>
                    <span class="text-slate-300 hidden sm:inline">•</span>
                    <span class="text-xs text-slate-500">Request date {{ $ticket->created_at->format('d M Y, H:i') }}</span>
                    <span class="text-slate-300 hidden sm:inline">•</span>
                    <span class="text-xs text-slate-500">Delivered date {{ $ticket->delivered_at ? $ticket->delivered_at->format('d M Y, H:i') : 'Belum diarahkan' }}</span>
                </div>

                <!-- Action Button (Pop-up Trigger) -->
                <div class="flex items-center gap-2">
                    @if($ticket->canBeRevisedBy($user))
                        <button type="button" @click="revisiModal = true"
                                class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-amber-50 hover:bg-amber-100 border border-amber-300 text-amber-800 text-xs font-semibold transition">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125"/>
                            </svg>
                            Edit &amp; Kembalikan
                        </button>
                    @endif

                    @if($showActionPanel)
                        <button type="button" @click="actionModal = true"
                                class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-slate-900 hover:bg-indigo-600 text-white text-xs font-semibold shadow-sm hover:shadow transition-all transform active:scale-95">
                            <svg class="w-4 h-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                            <span>Tindak Lanjut Role {{ $ticket->currentRole?->name }}</span>
                        </button>
                    @endif
                </div>
            </div>

            <!-- Judul & Deskripsi Tiket -->
            <div class="mt-5 space-y-3">
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight leading-snug">{{ $ticket->title }}</h1>
                <div class="p-4 rounded-xl bg-slate-50 border border-slate-100/90 text-xs sm:text-sm text-slate-700 leading-relaxed whitespace-pre-line">
                    {{ $ticket->description }}
                </div>
            </div>

            <!-- Dokumen / Gambar Lampiran -->
            @if($ticket->attachment_path)
                <div class="mt-4 p-3.5 rounded-xl bg-indigo-50/40 border border-indigo-100 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-9 h-9 rounded-lg bg-indigo-600/10 text-indigo-700 flex items-center justify-center text-base shrink-0">
                            {{ $ticket->isImageAttachment() ? '🖼️' : '📎' }}
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs font-bold text-slate-800 truncate">{{ $ticket->attachment_name ?? 'Lampiran Bukti Tiket' }}</p>
                            <p class="text-[11px] text-slate-400">{{ $ticket->isImageAttachment() ? 'Gambar Bukti Kendala / Request' : 'Lampiran Pengajuan Tiket' }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <button type="button"
                                @click="openDocPreview('{{ route('tickets.preview', $ticket) }}', '{{ route('tickets.attachment', $ticket) }}', '{{ addslashes($ticket->attachment_name ?? 'Dokumen Lampiran') }}')"
                                class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold shadow-xs transition">
                            Preview Dokumen
                        </button>
                        <a href="{{ route('tickets.attachment', $ticket) }}"
                           class="px-3 py-1.5 rounded-lg bg-white hover:bg-slate-50 border border-slate-200 text-xs font-semibold text-slate-700 transition">
                            Unduh
                        </a>
                    </div>
                </div>
                @if($ticket->isImageAttachment())
                    <div class="mt-3 rounded-xl overflow-hidden border border-slate-200 bg-white p-2">
                        <img src="{{ route('tickets.preview', $ticket) }}"
                             alt="{{ $ticket->attachment_name ?? 'Gambar Lampiran Tiket' }}"
                             class="max-h-72 w-auto mx-auto rounded-lg object-contain">
                    </div>
                @endif
            @endif

            <!-- Grid 4 Kolom Informasi Penting -->
            <div class="mt-6 pt-5 border-t border-slate-100 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
                <div class="p-3 rounded-xl bg-slate-50/70 border border-slate-100">
                    <span class="text-[10px] uppercase font-bold tracking-wider text-slate-400 block mb-0.5">Pemohon</span>
                    <p class="text-xs font-bold text-slate-900 truncate">{{ $ticket->creator?->name ?? '-' }}</p>
                    <span class="text-[11px] text-slate-500">{{ $ticket->creatorRole?->name ?? '-' }}</span>
                </div>

                <div class="p-3 rounded-xl bg-slate-50/70 border border-slate-100">
                    <span class="text-[10px] uppercase font-bold tracking-wider text-slate-400 block mb-0.5">Kategori</span>
                    <p class="text-xs font-bold text-slate-900 truncate">{{ $ticket->category?->name ?? '-' }}</p>
                    <span class="text-[11px] text-slate-500">Layanan SuperApp</span>
                </div>

                <div class="p-3 rounded-xl bg-slate-50/70 border border-slate-100">
                    <span class="text-[10px] uppercase font-bold tracking-wider text-slate-400 block mb-0.5">Posisi Hirarki</span>
                    <p class="text-xs font-bold text-indigo-700 truncate">Role {{ $ticket->currentRole?->name ?? '-' }}</p>
                    <span class="text-[11px] text-slate-500">Penanggung Jawab</span>
                </div>

                <div class="p-3 rounded-xl bg-amber-50/70 border border-amber-100">
                    <span class="text-[10px] uppercase font-bold tracking-wider text-amber-700 block mb-0.5">Poin Achievement</span>
                    <p class="text-xs font-bold text-slate-900">
                        {{ $ticket->achievement_points === null ? 'Belum diatur' : $ticket->achievement_points.' poin' }}
                    </p>
                    <span class="text-[11px] text-slate-500 block">
                        @if($ticket->achievement_points === null)
                            Kepala IT yang menentukan poin tiket ini.
                        @elseif($ticket->work_status === 'finished' || $ticket->finished_at)
                            Diterima {{ $ticket->assignedUser?->name ?? 'staff' }}.
                        @else
                            Diterima staff setelah tiket selesai.
                        @endif
                    </span>
                    @if($canSetAchievement ?? false)
                        <form action="{{ route('tickets.achievement', $ticket) }}" method="POST" class="mt-2 flex items-center gap-1.5">
                            @csrf
                            <input type="number" name="achievement_points" min="1" max="10" required
                                   value="{{ old('achievement_points', $ticket->achievement_points) }}"
                                   class="w-16 px-2 py-1 rounded-lg border border-amber-200 bg-white text-xs font-semibold">
                            <button type="submit" class="px-2 py-1 rounded-lg bg-slate-900 text-white text-[10px] font-semibold">Atur</button>
                        </form>
                    @endif
                </div>

                <div class="p-3 rounded-xl bg-slate-50/70 border border-slate-100">
                    <span class="text-[10px] uppercase font-bold tracking-wider text-slate-400 block mb-0.5">Petugas Pelaksana</span>
                    <p class="text-xs font-bold text-slate-900 truncate">
                        {{ $ticket->assignedUser?->name ?? 'Belum Diarahkan' }}
                    </p>
                    <span class="text-[11px] text-slate-500 truncate block">
                        {{ $ticket->assignedUser?->keahlian ? $ticket->assignedUser->keahlian : ($ticket->assignedUser ? 'Staff Pelaksana' : 'Menunggu Supervisor') }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Alur Hirarki (Stepper) -->
        <div class="px-6 py-3.5 bg-slate-50/80 border-t border-slate-100 flex items-center gap-2 flex-wrap text-xs">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 mr-2 flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/>
                </svg>
                Progres Hirarki:
            </span>
            @foreach($stepper as $idx => $step)
                @if($idx > 0)
                    <span class="text-slate-300 font-bold">→</span>
                @endif
                <span class="px-2.5 py-0.5 rounded-md font-semibold text-xs {{ $step['state'] === 'active' ? 'bg-slate-900 text-white shadow-xs' : ($step['state'] === 'completed' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'text-slate-400 bg-white border border-slate-200') }}">
                    @if($step['state'] === 'completed') ✓ @endif
                    {{ $step['role']->name }}
                </span>
            @endforeach
        </div>
    </div>

    <!-- 2. GRID KONTEN: Masalah & Progres + Riwayat & Timeline -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        <!-- Kolom Kiri: Masalah & Progres + Diskusi (lg:col-span-8) -->
        <div class="lg:col-span-8 space-y-6">

            <!-- KARTU MASALAH & PROGRES -->
            @if(!($approveOnly && $problemTotal === 0 && $problemPercent === null))
            <div class="bg-white rounded-2xl border border-slate-200/90 shadow-xs overflow-hidden">
                <div class="p-6 border-b border-slate-100 flex items-center justify-between gap-4 flex-wrap">
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-sm font-bold text-slate-900">Masalah &amp; Progres</h2>
                            <span class="px-2 py-0.5 rounded-full text-[11px] font-bold {{ ($problemPercent ?? 0) >= 100 ? 'bg-emerald-100 text-emerald-700' : 'bg-indigo-50 text-indigo-700' }}">
                                {{ $problemPercent === null ? '0%' : $problemPercent . '%' }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 mt-1">
                            Kepala menetapkan masalah. Staff mengisi sendiri sudah berapa persen yang dikerjakan.
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        @if($canReportProgress)
                            <button type="button" @click="progressModal = true"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white border border-slate-200 hover:bg-slate-50 text-xs font-semibold text-slate-700 shadow-xs transition">
                                <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125"/>
                                </svg>
                                Update Status
                            </button>
                        @endif

                        @if($canEditProblems)
                            <button type="button" @click="addProblemModal = true"
                                    class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-semibold transition">
                                + Tambah Poin Masalah
                            </button>
                        @endif
                    </div>
                </div>

                <div class="p-6">
                    <!-- Progress Bar Minimalis -->
                    <div class="space-y-1.5 mb-5">
                        <div class="h-2.5 rounded-full bg-slate-100 overflow-hidden">
                            <div class="h-full bg-emerald-500 transition-all duration-300" style="width: {{ $problemPercent ?? 0 }}%"></div>
                        </div>
                        <div class="flex items-center justify-between text-[11px] text-slate-400">
                            <span>
                                @if($ticket->hasWeightedProblems())
                                    Progres {{ $problemPercent ?? 0 }}% dihitung dari poin pekerjaan yang dicentang
                                    @if($ticket->assignedUser) · {{ $ticket->assignedUser->name }} @endif
                                @elseif($problemPercent === null)
                                    Kepala belum memecah tiket ini. Persen belum bisa diisi perkiraan.
                                @else
                                    Staff melaporkan {{ $problemPercent }}% selesai
                                    @if($ticket->assignedUser) · {{ $ticket->assignedUser->name }} @endif
                                @endif
                                @if($problemTotal > 0)
                                    · {{ $problemDone }} dari {{ $problemTotal }} masalah dicentang
                                @endif
                            </span>
                            @if($totalWeightConfigured > 0)
                                <span>Total Bobot: <strong class="text-slate-700">{{ $doneWeightConfigured }}%</strong> / {{ $totalWeightConfigured }}%</span>
                            @endif
                        </div>
                    </div>

                    <!-- Status Pengerjaan Aktif (jika ada) -->
                    @if($ticket->workStatusLabel() || $ticket->progress_note)
                        <div class="mb-4 p-3 rounded-xl border {{ $ticket->work_status === 'blocked' ? 'bg-rose-50 border-rose-200 text-rose-800' : 'bg-slate-50 border-slate-200/80 text-slate-700' }} flex items-start gap-2.5">
                            <span class="text-sm mt-0.5">{{ $ticket->work_status === 'blocked' ? '⚠️' : 'ℹ️' }}</span>
                            <div class="text-xs">
                                <span class="font-bold">{{ $ticket->workStatusLabel() ?? 'Status Pengerjaan' }}</span>
                                @if($ticket->work_status === 'blocked')
                                    <span class="font-normal text-rose-600">· ada masalah saat pengerjaan</span>
                                @endif
                                @if($ticket->progress_note)
                                    <p class="mt-0.5 text-slate-600">{{ $ticket->progress_note }}</p>
                                @endif
                            </div>
                        </div>
                    @endif

                    <!-- Form Inline Staff Progress Reporting (Jika Staff yang bertugas) -->
                    @if($canReportProgress)
                        <div class="mb-5 p-4 rounded-xl bg-slate-50/70 border border-slate-200">
                            <form action="{{ route('tickets.progress', $ticket) }}" method="POST" class="space-y-3">
                                @csrf
                                @if(!$ticket->hasWeightedProblems())
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-700 mb-1">Sudah dikerjakan (%)</label>
                                        <input type="number" name="progress_percent" min="0" max="100" required
                                               value="{{ old('progress_percent', $ticket->progress_percent) }}"
                                               class="w-28 px-3 py-1.5 rounded-lg border border-slate-200 text-xs focus:outline-none focus:border-slate-900 bg-white">
                                    </div>
                                @else
                                    <p class="text-[11px] text-slate-500">Centang poin di bawah. Persen bertambah sesuai bobot poin, bukan perkiraan.</p>
                                @endif

                                <div>
                                    <p class="text-xs font-semibold text-slate-700 mb-1.5">Status pengerjaan</p>
                                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                        @foreach(['delivered' => 'Delivered', 'in_progress' => 'In progress', 'blocked' => 'Blocked', 'finished' => 'Finished'] as $value => $label)
                                            <label class="flex items-center gap-2 p-2 rounded-lg border border-slate-200 bg-white text-xs cursor-pointer hover:border-slate-300">
                                                <input type="radio" name="work_status" value="{{ $value }}"
                                                       {{ old('work_status', $ticket->work_status) === $value ? 'checked' : '' }}>
                                                <span class="font-medium text-slate-900">{{ $label }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Catatan</label>
                                    <textarea name="note" rows="2" maxlength="500"
                                              placeholder="Kalau Blocked, tuliskan masalah yang menghambat. Selain itu catatan boleh dikosongkan."
                                              class="w-full px-3 py-2 rounded-lg border border-slate-200 text-xs focus:outline-none focus:border-slate-900 bg-white">{{ old('note', $ticket->progress_note) }}</textarea>
                                </div>

                                <button type="submit" class="px-4 py-1.5 rounded-lg bg-slate-900 hover:bg-slate-800 text-white text-xs font-semibold">
                                    Simpan Progres
                                </button>
                            </form>
                        </div>
                    @endif

                    <!-- Daftar Checklist Poin Masalah -->
                    @if($ticket->problems->isNotEmpty())
                        <div class="space-y-2">
                            @foreach($ticket->problems as $problem)
                                <div class="flex items-center justify-between gap-3 p-3 rounded-xl border transition {{ $problem->is_done ? 'bg-emerald-50/30 border-emerald-200' : 'bg-white border-slate-200 hover:border-slate-300' }}">
                                    <div class="flex items-start gap-3 min-w-0 flex-1">
                                        @if($canToggleProblems)
                                            <form action="{{ route('tickets.problems.toggle', [$ticket, $problem]) }}" method="POST">
                                                @csrf
                                                <button type="submit" title="Klik untuk menandai selesai / batal"
                                                        class="mt-0.5 w-4 h-4 rounded-md border flex items-center justify-center text-[10px] font-bold transition {{ $problem->is_done ? 'bg-emerald-600 border-emerald-600 text-white' : 'border-slate-300 bg-white text-transparent hover:border-indigo-500' }}">
                                                    ✓
                                                </button>
                                            </form>
                                        @else
                                            <span class="mt-0.5 w-4 h-4 rounded-md border flex items-center justify-center text-[10px] font-bold {{ $problem->is_done ? 'bg-emerald-600 border-emerald-600 text-white' : 'border-slate-300 text-transparent' }}">
                                                ✓
                                            </span>
                                        @endif

                                        <div class="min-w-0 flex-1">
                                            <p class="text-xs font-semibold {{ $problem->is_done ? 'text-slate-400 line-through' : 'text-slate-800' }}">
                                                {{ $problem->title }}
                                            </p>
                                            @if($problem->is_done)
                                                <p class="text-[11px] text-emerald-700 mt-0.5 flex items-center gap-1">
                                                    <span>Selesai oleh {{ $problem->doneBy?->name ?? 'staff' }}</span>
                                                    @if($problem->done_at)
                                                        <span>• {{ $problem->done_at->format('d M, H:i') }}</span>
                                                    @endif
                                                </p>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-2 shrink-0">
                                        @if($problem->weight_percent > 0)
                                            <span class="px-2 py-0.5 rounded-md text-[11px] font-bold {{ $problem->is_done ? 'bg-emerald-100 text-emerald-800' : 'bg-indigo-50 text-indigo-700 border border-indigo-100' }}">
                                                +{{ $problem->weight_percent }}%
                                            </span>
                                        @endif

                                        @if($canEditProblems)
                                            <form action="{{ route('tickets.problems.destroy', [$ticket, $problem]) }}" method="POST" onsubmit="return confirm('Hapus poin ini?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" title="Hapus poin pekerjaan" class="p-1 rounded text-slate-300 hover:text-rose-600 transition">
                                                    ✕
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-6 border border-dashed border-slate-200 rounded-xl bg-slate-50/50">
                            <p class="text-xs text-slate-500 font-medium">Belum ada poin pekerjaan yang ditetapkan.</p>
                            @if($canEditProblems)
                                <button type="button" @click="addProblemModal = true" class="mt-2 text-xs font-bold text-indigo-600 hover:underline">
                                    + Tambah Rincian Poin &amp; Bobot % Sekarang
                                </button>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
            @endif

            <!-- KARTU RIWAYAT & DISKUSI -->
            <div class="bg-white rounded-2xl border border-slate-200/90 shadow-xs overflow-hidden">
                <div class="p-6 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-bold text-slate-900">Riwayat &amp; Catatan Diskusi</h2>
                        <p class="text-xs text-slate-400 mt-0.5">Semua jejak keputusan dan komunikasi antar role</p>
                    </div>
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">
                        {{ $ticket->logs->count() }} Aktivitas
                    </span>
                </div>

                <!-- Timeline Feed -->
                <div class="divide-y divide-slate-100 max-h-[500px] overflow-y-auto">
                    @forelse($ticket->logs as $log)
                        @php $badge = $log->actionBadge(); @endphp
                        <div class="p-5 text-xs hover:bg-slate-50/50 transition">
                            <div class="flex items-center justify-between gap-3 mb-1.5">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <div class="w-6 h-6 rounded-full bg-slate-100 text-slate-700 font-bold flex items-center justify-center text-[10px]">
                                        {{ strtoupper(substr($log->user?->name ?? 'U', 0, 1)) }}
                                    </div>
                                    <span class="font-bold text-slate-900">{{ $log->user?->name ?? 'Sistem' }}</span>
                                    <span class="text-[11px] text-slate-400">({{ $log->user?->role?->name ?? '-' }})</span>
                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-100">
                                        {{ $badge['label'] }}
                                    </span>
                                    @if($log->fromRole && $log->toRole && $log->from_role_id !== $log->to_role_id)
                                        <span class="text-[11px] text-slate-400">({{ $log->fromRole->name }} → {{ $log->toRole->name }})</span>
                                    @endif
                                </div>
                                <span class="text-[11px] text-slate-400 shrink-0">{{ $log->created_at->format('d M Y, H:i') }}</span>
                            </div>
                            <p class="text-slate-600 leading-relaxed pl-8 whitespace-pre-line">{{ $log->note }}</p>
                        </div>
                    @empty
                        <div class="text-center py-8 text-xs text-slate-400">
                            Belum ada riwayat aktivitas pada tiket ini.
                        </div>
                    @endforelse
                </div>

                <!-- Quick Comment Form -->
                <form action="{{ route('tickets.comment', $ticket) }}" method="POST" class="p-4 bg-slate-50 border-t border-slate-100 flex gap-2">
                    @csrf
                    <input type="text" name="note" required placeholder="Tulis balasan atau catatan baru..."
                           class="flex-1 px-3.5 py-2.5 rounded-xl bg-white border border-slate-200 text-xs focus:outline-none focus:border-slate-900 shadow-xs">
                    <button type="submit" class="px-4 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-semibold shrink-0 shadow-xs transition">
                        Kirim Balasan
                    </button>
                </form>
            </div>
        </div>

        <!-- Kolom Kanan: Ringkasan & Tindakan Cepat (lg:col-span-4) -->
        <div class="lg:col-span-4 space-y-6">

            <!-- Card: Tindakan Tersedia (Jika Berhak) -->
            @if($showActionPanel)
                <div class="bg-gradient-to-br from-slate-900 to-slate-800 rounded-2xl p-5 text-white shadow-md space-y-4">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-amber-400/20 text-amber-400 flex items-center justify-center font-bold">
                            ⚡
                        </div>
                        <div>
                            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-300">Tindak Lanjut Role</h3>
                            <p class="text-xs font-semibold text-white">Role {{ $ticket->currentRole?->name }}</p>
                        </div>
                    </div>

                    <p class="text-xs text-slate-300 leading-relaxed">
                        @if($isWorkDesk)
                            Cek apakah isi permintaan sesuai level yang dipilih pemohon. Kalau sesuai, proses dan bagikan ke staff. Kalau tidak, tolak.
                        @elseif($approveOnly)
                            Persetujuan diperlukan untuk meneruskan tiket ke level atasan berikutnya.
                        @else
                            Tetapkan prioritas dan tentukan tindak lanjut hirarki untuk tiket ini.
                        @endif
                    </p>

                    <button type="button" @click="actionModal = true"
                            class="w-full py-2.5 rounded-xl bg-white hover:bg-slate-100 text-slate-900 text-xs font-bold shadow-sm transition flex items-center justify-center gap-2">
                        <span>Buka Menu Tindak Lanjut (Pop-up)</span>
                        <span>→</span>
                    </button>
                </div>
            @endif

            <!-- Card: Timeline Tanggal Tiket -->
            <div class="bg-white rounded-2xl border border-slate-200/90 shadow-xs p-5 space-y-4">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">Timeline Tiket</h3>
                <div class="space-y-3 text-xs">
                    <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                        <span class="text-slate-500">Request date</span>
                        <span class="font-semibold text-slate-800">{{ $ticket->created_at->format('d M Y, H:i') }}</span>
                    </div>
                    <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                        <span class="text-slate-500">Delivered date</span>
                        <span class="font-semibold text-slate-800">
                            {{ $ticket->delivered_at ? $ticket->delivered_at->format('d M Y, H:i') : 'Belum diarahkan' }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500">Terakhir Update</span>
                        <span class="font-semibold text-slate-800">{{ $ticket->updated_at->diffForHumans() }}</span>
                    </div>
                </div>
            </div>

            <!-- Card: Staff Pelaksana -->
            @if($ticket->assignedUser)
                <div class="bg-white rounded-2xl border border-slate-200/90 shadow-xs p-5 space-y-3">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">Staff Penanggung Jawab</h3>
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-indigo-50 border border-indigo-200 text-indigo-700 font-bold flex items-center justify-center text-sm">
                            {{ strtoupper(substr($ticket->assignedUser->name, 0, 1)) }}
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs font-bold text-slate-900 truncate">{{ $ticket->assignedUser->name }}</p>
                            <p class="text-[11px] text-slate-500 truncate">{{ $ticket->assignedUser->keahlian ?? 'Staff Pelaksana' }}</p>
                        </div>
                    </div>
                </div>
            @endif

        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- POP-UP MODAL 1: TINDAK LANJUT TIKET (Clean & Intuitive Popup Dialog)       -->
    <!-- ========================================================================= -->
    @if($showActionPanel)
    <div x-show="actionModal" x-cloak
         @keydown.escape.window="actionModal = false"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
        <div @click.outside="actionModal = false"
             class="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-xl w-full max-h-[90vh] flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-150">

            <!-- Modal Header -->
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between gap-3 bg-slate-50/50">
                <div class="flex items-center gap-2.5">
                    <div class="w-7 h-7 rounded-lg bg-slate-900 text-amber-400 flex items-center justify-center font-bold text-xs">
                        ⚡
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">Tindak Lanjut Role {{ $ticket->currentRole?->name }}</h3>
                        <p class="text-[11px] text-slate-500">Tiket #{{ $ticket->ticket_number }}</p>
                    </div>
                </div>
                <button type="button" @click="actionModal = false"
                        class="w-7 h-7 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 flex items-center justify-center text-sm transition">
                    ✕
                </button>
            </div>

            <!-- Modal Body (Scrollable Form) -->
            <form id="actionTicketForm" action="{{ route('tickets.action', $ticket) }}" method="POST"
                  class="flex-1 overflow-y-auto p-6 space-y-5 text-xs">
                @csrf

                <!-- Kasus Approve Only (Hirarki Persetujuan Ringkas) -->
                @if($approveOnly)
                    <div class="p-3.5 rounded-xl bg-indigo-50/60 border border-indigo-100 text-xs text-indigo-900">
                        Klik <strong>Setuju, lanjut ke {{ $nextSuperiorRole->name }}</strong> untuk melimpahkan tiket ke atasan hirarki.
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1.5">Catatan (opsional)</label>
                        <textarea name="note" rows="3" maxlength="1000" placeholder="Tambahkan catatan bila perlu..."
                                  class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs focus:outline-none focus:border-slate-900">{{ old('note') }}</textarea>
                    </div>

                    <div class="space-y-2 pt-2">
                        <button type="submit" name="action_type" value="approve_step"
                                class="w-full py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-semibold text-xs transition">
                            Setuju, lanjut ke {{ $nextSuperiorRole->name }}
                        </button>
                        <button type="submit" name="action_type" value="reject"
                                class="w-full py-2 rounded-xl border border-rose-200 text-rose-700 text-xs font-semibold hover:bg-rose-50 transition">
                            Tolak
                        </button>
                    </div>
                @else
                    <!-- 1. SET PRIORITAS TIKET -->
                    <div>
                        <label class="block text-xs font-bold text-slate-800 mb-1">1. Set Prioritas Tiket</label>
                        @if($canDirect && $roleStaff->isNotEmpty())
                            <select name="priority_rank"
                                    class="w-full px-3 py-2.5 rounded-xl border border-slate-200 text-xs bg-white font-semibold text-slate-800 focus:outline-none focus:border-slate-900">
                                @for($level = 1; $level <= 4; $level++)
                                    <option value="{{ $level }}" {{ (int) old('priority_rank', $ticket->priority_rank ?: 2) === $level ? 'selected' : '' }}>
                                        {{ \App\Models\RolePriorityRoute::labelFor($level) }}
                                    </option>
                                @endfor
                            </select>
                            <p class="text-[11px] text-slate-400 mt-1">
                                Usulan pemohon: <strong>{{ $ticket->priority_rank ? $ticket->priorityLabel() : 'Belum dipilih' }}</strong>. Supervisor dapat menyesuaikan level prioritas.
                            </p>
                        @elseif($ticket->currentRole?->head_user_id)
                            <p class="text-xs text-slate-600 font-semibold bg-slate-50 p-2.5 rounded-lg border border-slate-200">
                                Prioritas: {{ $ticket->priorityLabel() }}
                            </p>
                        @else
                            <select name="priority" class="w-full px-3 py-2.5 rounded-xl border border-slate-200 text-xs bg-white focus:outline-none focus:border-slate-900">
                                <option value="low" {{ old('priority', $ticket->priority) === 'low' ? 'selected' : '' }}>Rendah (Low)</option>
                                <option value="medium" {{ (!old('priority', $ticket->priority) || old('priority', $ticket->priority) === 'medium') ? 'selected' : '' }}>Normal (Medium)</option>
                                <option value="high" {{ old('priority', $ticket->priority) === 'high' ? 'selected' : '' }}>Tinggi (High)</option>
                                <option value="urgent" {{ old('priority', $ticket->priority) === 'urgent' ? 'selected' : '' }}>Mendesak (Urgent)</option>
                            </select>
                        @endif
                    </div>

                    <!-- 2. ARAHKAN KE STAFF (Jika Supervisor / Work Desk) -->
                    @if($canDirect && $roleStaff->isNotEmpty())
                        <div x-show="actionType === 'in_progress'" x-cloak class="p-4 rounded-xl bg-slate-50 border border-slate-200/80 space-y-3"
                             x-data="{
                                 deliveryPoints: [],
                                 addDeliveryPoint() {
                                     this.deliveryPoints.push({ title: '', weight_percent: 50 });
                                 },
                                 removeDeliveryPoint(i) {
                                     this.deliveryPoints.splice(i, 1);
                                 }
                             }">
                            <div>
                                <label class="block text-xs font-bold text-slate-800 mb-1">2. Arahkan ke staff (Delivery Tugas)</label>
                                <select name="assigned_user_id"
                                        class="w-full px-3 py-2 rounded-lg border border-slate-200 text-xs bg-white focus:outline-none focus:border-slate-900">
                                    <option value="">Pilih staff sesuai keahlian...</option>
                                    @foreach($roleStaff as $staff)
                                        @continue((int) $staff->id === (int) $ticket->currentRole?->head_user_id)
                                        <option value="{{ $staff->id }}" {{ (int) old('assigned_user_id', $ticket->assigned_user_id) === (int) $staff->id ? 'selected' : '' }}>
                                            {{ $staff->name }}@if($staff->keahlian) — {{ $staff->keahlian }}@endif
                                        </option>
                                    @endforeach
                                </select>
                                <p class="text-[11px] text-slate-400 mt-1">Pilih staff pelaksana yang akan mengerjakan tiket ini.</p>
                            </div>
                            @if($canSetAchievement ?? false)
                            <div>
                                <label class="block text-xs font-bold text-slate-800 mb-1">Poin achievement tiket</label>
                                <input type="number" name="achievement_points" min="1" max="10"
                                       value="{{ old('achievement_points', $ticket->achievement_points) }}"
                                       placeholder="1–10"
                                       class="w-28 px-3 py-2 rounded-lg border border-slate-200 text-xs bg-white focus:outline-none focus:border-slate-900">
                                <p class="text-[11px] text-slate-400 mt-1">Isi 1 sampai 10. Staff yang mengerjakan menerima poin ini setelah tiket selesai.</p>
                            </div>
                            @endif

                            <div class="pt-2 border-t border-slate-200/60">
                                <div class="flex items-center justify-between gap-2 mb-2">
                                    <span class="text-[11px] font-bold text-slate-700">Tambah Poin Pekerjaan &amp; % Sebelum Delivery</span>
                                    <button type="button" @click="addDeliveryPoint()"
                                            class="px-2 py-0.5 rounded-md bg-white border border-slate-200 hover:bg-slate-100 text-[10px] font-bold text-indigo-600">
                                        + Input Poin (%)
                                    </button>
                                </div>

                                <template x-if="deliveryPoints.length === 0">
                                    <p class="text-[11px] text-slate-400 italic">
                                        @if($ticket->problems->count() > 0)
                                            Sudah ada {{ $ticket->problems->count() }} poin pekerjaan ({{ (int) $ticket->problems->sum('weight_percent') }}%). Klik + Input Poin (%) jika ingin menambah lagi.
                                        @else
                                            Klik + Input Poin (%) untuk langsung menambahkan rincian pekerjaan beserta bobot % sebelum diserahkan ke staff.
                                        @endif
                                    </p>
                                </template>

                                <div class="space-y-1.5">
                                    <template x-for="(pt, idx) in deliveryPoints" :key="idx">
                                        <div class="flex items-center gap-2">
                                            <input type="text"
                                                   :name="'problem_items[' + idx + '][title]'"
                                                   x-model="pt.title"
                                                   placeholder="Detail pekerjaan untuk staff..."
                                                   class="flex-1 px-2.5 py-1.5 rounded-lg border border-slate-200 bg-white text-xs">
                                            <div class="relative w-20 shrink-0">
                                                <input type="number"
                                                       :name="'problem_items[' + idx + '][weight_percent]'"
                                                       x-model="pt.weight_percent"
                                                       min="1" max="100"
                                                       class="w-full pl-2 pr-5 py-1.5 rounded-lg border border-slate-200 bg-white text-xs font-bold">
                                                <span class="absolute right-1.5 top-1/2 -translate-y-1/2 text-[10px] font-bold text-slate-400">%</span>
                                            </div>
                                            <button type="button" @click="removeDeliveryPoint(idx)" class="p-1 text-slate-400 hover:text-rose-600">✕</button>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- 3. PILIHAN TINDAKAN (Card Selector) -->
                    <div class="space-y-2">
                        <label class="block text-xs font-bold text-slate-800 mb-1">3. Pilih Tindakan</label>

                        @if($ticket->workflow_type === 'tiered_approval')
                            <label class="flex items-start gap-2.5 p-3 rounded-xl border cursor-pointer transition"
                                   :class="actionType === 'approve_step' ? 'bg-slate-50 border-slate-900 ring-1 ring-slate-900' : 'border-slate-200 hover:bg-slate-50/50'">
                                <input type="radio" name="action_type" value="approve_step" x-model="actionType" class="mt-0.5">
                                <div>
                                    <span class="font-bold text-slate-900 block">
                                        {{ $ticket->isAtFinalApprovalStage() ? 'Setujui Final (Selesai)' : 'Setujui & Teruskan ke ' . $nextSuperiorRole?->name }}
                                    </span>
                                    <span class="text-[11px] text-slate-400">Lanjutkan verifikasi tiket</span>
                                </div>
                            </label>
                        @endif

                        <label class="flex items-start gap-2.5 p-3 rounded-xl border cursor-pointer transition"
                               :class="actionType === 'in_progress' ? 'bg-indigo-50/50 border-indigo-600 ring-1 ring-indigo-600' : 'border-slate-200 hover:bg-slate-50/50'">
                            <input type="radio" name="action_type" value="in_progress" x-model="actionType" class="mt-0.5">
                            <div>
                                <span class="font-medium text-slate-900 block">{{ ($canDirect ?? false) && $roleStaff->isNotEmpty() ? 'Sesuai, proses dan bagikan ke staff' : 'Tandai Sedang Diproses' }}</span>
                                @if(($canDirect ?? false) && $roleStaff->isNotEmpty())
                                    <span class="text-[11px] text-slate-400">Level pemohon cocok dengan isi permintaan</span>
                                @endif
                            </div>
                        </label>

                        @if($nextSuperiorRole)
                            <label class="flex items-start gap-2.5 p-3 rounded-xl border cursor-pointer transition"
                                   :class="actionType === 'escalate' ? 'bg-slate-50 border-slate-900 ring-1 ring-slate-900' : 'border-slate-200 hover:bg-slate-50/50'">
                                <input type="radio" name="action_type" value="escalate" x-model="actionType" class="mt-0.5">
                                <div>
                                    <span class="font-medium text-slate-900 block">Eskalasi ke {{ $nextSuperiorRole->name }}</span>
                                    <span class="text-[11px] text-slate-400">Teruskan ke atasan hirarki</span>
                                </div>
                            </label>
                        @endif

                        @if($subordinateRoles->count() > 0)
                            <label class="flex items-start gap-2.5 p-3 rounded-xl border cursor-pointer transition"
                                   :class="actionType === 'dispose' ? 'bg-slate-50 border-slate-900 ring-1 ring-slate-900' : 'border-slate-200 hover:bg-slate-50/50'">
                                <input type="radio" name="action_type" value="dispose" x-model="actionType" class="mt-0.5">
                                <div class="w-full">
                                    <span class="font-medium text-slate-900 block">Disposisi ke Bawahan</span>
                                    <div x-show="actionType === 'dispose'" x-cloak class="mt-2">
                                        <select name="target_subordinate_role_id" class="w-full px-2.5 py-1.5 rounded-lg border border-slate-200 text-xs bg-white">
                                            @foreach($subordinateRoles as $sub)
                                                <option value="{{ $sub->id }}">Role {{ $sub->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </label>
                        @endif

                        <label class="flex items-start gap-2.5 p-3 rounded-xl border cursor-pointer transition"
                               :class="actionType === 'resolve' ? 'bg-slate-50 border-slate-900 ring-1 ring-slate-900' : 'border-slate-200 hover:bg-slate-50/50'">
                            <input type="radio" name="action_type" value="resolve" x-model="actionType" class="mt-0.5">
                            <div>
                                <span class="font-medium text-slate-900 block">Selesaikan Tiket</span>
                                <span class="text-[11px] text-slate-400">Tandai tiket telah rampung ditangani</span>
                            </div>
                        </label>

                        <label class="flex items-start gap-2.5 p-3 rounded-xl border cursor-pointer transition"
                               :class="actionType === 'reject' ? 'bg-rose-50 border-rose-500 ring-1 ring-rose-500' : 'border-slate-200 hover:bg-rose-50/30'">
                            <input type="radio" name="action_type" value="reject" x-model="actionType" class="mt-0.5">
                            <div>
                                <span class="font-medium text-rose-700 block">{{ ($canDirect ?? false) && $roleStaff->isNotEmpty() ? 'Tidak sesuai, tolak' : 'Tolak Tiket' }}</span>
                                @if(($canDirect ?? false) && $roleStaff->isNotEmpty())
                                    <span class="text-[11px] text-rose-400">Isi permintaan tidak cocok dengan level yang dipilih pemohon</span>
                                @endif
                            </div>
                        </label>
                    </div>

                    <!-- 4. CATATAN KEPUTUSAN -->
                    <div>
                        <label class="block text-xs font-bold text-slate-800 mb-1">Catatan Keputusan</label>
                        <textarea name="note" rows="3" required
                                  :placeholder="actionType === 'reject' ? 'Jelaskan kenapa level pemohon tidak sesuai...' : 'Tulis catatan tindak lanjut...'"
                                  class="w-full px-3 py-2.5 rounded-xl border border-slate-200 text-xs focus:outline-none focus:border-slate-900 shadow-xs">{{ old('note') }}</textarea>
                    </div>
                @endif
            </form>

            <!-- Modal Footer -->
            @if(!$approveOnly)
                <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex items-center justify-end gap-2.5">
                    <button type="button" @click="actionModal = false"
                            class="px-4 py-2 rounded-xl border border-slate-200 text-xs font-semibold text-slate-600 hover:bg-slate-100 transition">
                        Batal
                    </button>
                    <button type="submit" form="actionTicketForm"
                            class="px-5 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold shadow-xs transition">
                        Simpan Keputusan
                    </button>
                </div>
            @endif
        </div>
    </div>
    @endif

    <!-- ========================================================================= -->
    <!-- POP-UP MODAL 2: UPDATE PROGRES & STATUS PENGERJAAN (Staff)               -->
    <!-- ========================================================================= -->
    @if($canReportProgress)
    <div x-show="progressModal" x-cloak
         @keydown.escape.window="progressModal = false"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
        <div @click.outside="progressModal = false"
             class="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-md w-full overflow-hidden animate-in fade-in zoom-in-95 duration-150">

            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                <h3 class="text-xs font-bold text-slate-900">Update Status &amp; Progres Pengerjaan</h3>
                <button type="button" @click="progressModal = false" class="text-slate-400 hover:text-slate-700">✕</button>
            </div>

            <form action="{{ route('tickets.progress', $ticket) }}" method="POST" class="p-5 space-y-4 text-xs">
                @csrf

                @if(!$ticket->hasWeightedProblems())
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Sudah dikerjakan (%)</label>
                        <input type="number" name="progress_percent" min="0" max="100" required
                               value="{{ old('progress_percent', $ticket->progress_percent ?? 0) }}"
                               class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs font-bold focus:outline-none focus:border-slate-900">
                    </div>
                @else
                    <div class="p-2.5 rounded-lg bg-indigo-50/60 text-indigo-800 text-[11px]">
                        ℹ️ Persen progres dihitung otomatis berdasarkan centang poin pekerjaan berbobot di tiket.
                    </div>
                @endif

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Status pengerjaan</label>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach(['delivered' => 'Delivered', 'in_progress' => 'In progress', 'blocked' => 'Blocked', 'finished' => 'Finished'] as $value => $label)
                            <label class="flex items-center gap-2 p-2.5 rounded-xl border cursor-pointer {{ old('work_status', $ticket->work_status) === $value ? 'bg-slate-900 text-white font-bold' : 'border-slate-200 hover:bg-slate-50' }}">
                                <input type="radio" name="work_status" value="{{ $value }}" class="sr-only"
                                       {{ old('work_status', $ticket->work_status) === $value ? 'checked' : '' }}
                                       onchange="this.form.querySelectorAll('label').forEach(l => l.classList.remove('bg-slate-900', 'text-white', 'font-bold')); this.parentElement.classList.add('bg-slate-900', 'text-white', 'font-bold');">
                                <span class="text-xs">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Catatan</label>
                    <textarea name="note" rows="3" maxlength="500"
                              placeholder="Jika ada kendala (Blocked) atau progres penting, tuliskan di sini..."
                              class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs focus:outline-none focus:border-slate-900">{{ old('note', $ticket->progress_note) }}</textarea>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                    <button type="button" @click="progressModal = false"
                            class="px-3.5 py-1.5 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 font-medium">Batal</button>
                    <button type="submit"
                            class="px-4 py-1.5 rounded-lg bg-slate-900 hover:bg-slate-800 text-white font-bold">Simpan Progres</button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <!-- ========================================================================= -->
    <!-- POP-UP MODAL 3: TAMBAH POIN PEKERJAAN & BOBOT % (Supervisor / Head)        -->
    <!-- ========================================================================= -->
    @if($canEditProblems)
    <div x-show="addProblemModal" x-cloak
         @keydown.escape.window="addProblemModal = false"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
        <div @click.outside="addProblemModal = false"
             class="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-xl w-full max-h-[85vh] flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-150"
             x-data="{
                 existingWeight: {{ $totalWeightConfigured }},
                 rows: [
                     { title: '', weight_percent: {{ max(0, min(100, 100 - $totalWeightConfigured)) ?: 30 }} }
                 ],
                 addRow() {
                     this.rows.push({ title: '', weight_percent: 20 });
                 },
                 removeRow(idx) {
                     if (this.rows.length > 1) this.rows.splice(idx, 1);
                 },
                 distributeEvenly() {
                     const remaining = Math.max(0, 100 - this.existingWeight);
                     const target = remaining > 0 ? remaining : 100;
                     const count = this.rows.length;
                     const base = Math.floor(target / count);
                     let rem = target - (base * count);
                     this.rows.forEach((r, i) => {
                         r.weight_percent = base + (i < rem ? 1 : 0);
                     });
                 },
                 newWeightSum() {
                     return this.rows.reduce((sum, r) => sum + (parseInt(r.weight_percent, 10) || 0), 0);
                 }
             }">

            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                <div>
                    <h3 class="text-sm font-bold text-slate-900">Tetapkan masalah / Poin Detail Pekerjaan &amp; Bobot (%)</h3>
                    <p class="text-[11px] text-slate-500">Setiap poin yang dicentang selesai oleh staff akan menambah progres % otomatis sesuai bobotnya.</p>
                </div>
                <button type="button" @click="addProblemModal = false" class="text-slate-400 hover:text-slate-700">✕</button>
            </div>

            <form id="addProblemForm" action="{{ route('tickets.problems.store', $ticket) }}" method="POST"
                  class="flex-1 overflow-y-auto p-6 space-y-3.5 text-xs">
                @csrf

                <div class="flex items-center justify-between gap-2 pb-1 border-b border-slate-100">
                    <span class="text-[11px] text-slate-500">
                        Bobot: <strong :class="(existingWeight + newWeightSum()) === 100 ? 'text-emerald-600' : 'text-amber-600'" x-text="(existingWeight + newWeightSum()) + '%'"></strong>
                    </span>
                    <button type="button" @click="distributeEvenly()"
                            class="px-2.5 py-1 rounded-md bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-[10px] font-bold">
                        Bagi Rata % Otomatis
                    </button>
                </div>

                <div class="space-y-2">
                    <template x-for="(row, idx) in rows" :key="idx">
                        <div class="flex items-center gap-2">
                            <input type="text"
                                   :name="'items[' + idx + '][title]'"
                                   x-model="row.title"
                                   required
                                   placeholder="Contoh: Pengecekan switch &amp; kabel jaringan ruang lab..."
                                   class="flex-1 px-3 py-2 rounded-xl border border-slate-200 text-xs focus:outline-none focus:border-slate-900">
                            <div class="relative w-24 shrink-0">
                                <input type="number"
                                       :name="'items[' + idx + '][weight_percent]'"
                                       x-model="row.weight_percent"
                                       min="1" max="100" required
                                       class="w-full pl-2.5 pr-6 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-800 focus:outline-none focus:border-slate-900">
                                <span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-400">%</span>
                            </div>
                            <button type="button" @click="removeRow(idx)" x-show="rows.length > 1"
                                    class="p-2 rounded-xl text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition">
                                ✕
                            </button>
                        </div>
                    </template>
                </div>

                <button type="button" @click="addRow()"
                        class="w-full py-2 border border-dashed border-slate-300 rounded-xl text-slate-600 hover:bg-slate-50 font-semibold text-xs transition">
                    + Tambah Poin Pekerjaan
                </button>
            </form>

            <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex items-center justify-end gap-2.5">
                <button type="button" @click="addProblemModal = false"
                        class="px-4 py-2 rounded-xl border border-slate-200 text-xs font-semibold text-slate-600 hover:bg-slate-100">
                    Batal
                </button>
                <button type="submit" form="addProblemForm"
                        class="px-5 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold shadow-xs">
                    Simpan Masalah &amp; Bobot %
                </button>
            </div>
        </div>
    </div>
    @endif

    <!-- ========================================================================= -->
    <!-- POP-UP MODAL 4: EDIT & KEMBALIKAN (Revisi Tiket)                          -->
    <!-- ========================================================================= -->
    @if($ticket->canBeRevisedBy($user))
    <div x-show="revisiModal" x-cloak
         @keydown.escape.window="revisiModal = false"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
        <div @click.outside="revisiModal = false"
             class="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-md w-full overflow-hidden animate-in fade-in zoom-in-95 duration-150">

            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between bg-amber-50/50">
                <h3 class="text-xs font-bold text-amber-900">Edit tiket selesai</h3>
                <button type="button" @click="revisiModal = false" class="text-slate-400 hover:text-slate-700">✕</button>
            </div>

            <form action="{{ route('tickets.reopen', $ticket) }}" method="POST" class="p-5 space-y-4 text-xs">
                @csrf
                <input type="hidden" name="from_revisi" value="1">
                <p class="text-[11px] text-slate-500">
                    Gunakan ini jika hasil pengerjaan belum sesuai. Tiket kembali ke staff yang ditugaskan.
                </p>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Catatan Kekurangan / Revisi</label>
                    <textarea name="note" rows="3" required minlength="3" placeholder="Tuliskan bagian yang belum sesuai..."
                              class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs focus:outline-none focus:border-slate-900">{{ old('note') }}</textarea>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                    <button type="button" @click="revisiModal = false"
                            class="px-3.5 py-1.5 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 font-medium">Batal</button>
                    <button type="submit"
                            class="px-4 py-1.5 rounded-lg bg-slate-900 hover:bg-slate-800 text-white font-bold">Edit dan kembalikan</button>
                </div>
            </form>
        </div>
    </div>
    @endif

</div>
@endsection
