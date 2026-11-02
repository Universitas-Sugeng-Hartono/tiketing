@extends('layouts.app')

@section('content')
@php
    $statusBadges = [
        'open' => ['label' => 'Baru Masuk', 'bg' => 'bg-amber-50', 'text' => 'text-amber-700', 'border' => 'border-amber-200', 'dot' => 'bg-amber-500'],
        'in_progress' => ['label' => 'Diproses', 'bg' => 'bg-sky-50', 'text' => 'text-sky-700', 'border' => 'border-sky-200', 'dot' => 'bg-sky-500'],
        'escalated' => ['label' => 'Eskalasi', 'bg' => 'bg-violet-50', 'text' => 'text-violet-700', 'border' => 'border-violet-200', 'dot' => 'bg-violet-500'],
        'waiting_next_approval' => ['label' => 'Menunggu Approval', 'bg' => 'bg-indigo-50', 'text' => 'text-indigo-700', 'border' => 'border-indigo-200', 'dot' => 'bg-indigo-500'],
        'resolved' => ['label' => 'Selesai', 'bg' => 'bg-emerald-50', 'text' => 'text-emerald-700', 'border' => 'border-emerald-200', 'dot' => 'bg-emerald-500'],
        'rejected' => ['label' => 'Ditolak', 'bg' => 'bg-rose-50', 'text' => 'text-rose-700', 'border' => 'border-rose-200', 'dot' => 'bg-rose-500'],
        'closed' => ['label' => 'Ditutup', 'bg' => 'bg-slate-50', 'text' => 'text-slate-700', 'border' => 'border-slate-200', 'dot' => 'bg-slate-400'],
    ];

    $priorityBadges = [
        'urgent' => ['label' => 'Urgent', 'bg' => 'bg-rose-50', 'text' => 'text-rose-700', 'border' => 'border-rose-200', 'dot' => 'bg-rose-500'],
        'high' => ['label' => 'Tinggi', 'bg' => 'bg-amber-50', 'text' => 'text-amber-700', 'border' => 'border-amber-200', 'dot' => 'bg-amber-500'],
        'medium' => ['label' => 'Sedang', 'bg' => 'bg-sky-50', 'text' => 'text-sky-700', 'border' => 'border-sky-200', 'dot' => 'bg-sky-500'],
        'low' => ['label' => 'Rendah', 'bg' => 'bg-slate-50', 'text' => 'text-slate-700', 'border' => 'border-slate-200', 'dot' => 'bg-slate-400'],
    ];
@endphp

@if($user->isSuperuser())
    {{-- ============================================================== --}}
    {{-- DASHBOARD EKSEKUTIF SUPERADMIN (CLEAN & PROFESSIONAL SAAS UI)  --}}
    {{-- ============================================================== --}}

    <!-- Top Banner / Header Superadmin -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 mb-6 bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
        <div class="flex items-center gap-3.5">
            <div class="w-12 h-12 rounded-xl bg-gradient-to-tr from-indigo-600 to-indigo-500 flex items-center justify-center text-white shadow-md shadow-indigo-500/20 shrink-0">
                @include('partials.icon', ['name' => 'ticket', 'class' => 'w-6 h-6'])
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-lg font-bold text-slate-900 tracking-tight">Dashboard Super Admin</h1>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200/80">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        Sistem Aktif
                    </span>
                </div>
                <p class="text-xs text-slate-500 mt-0.5">
                    Ringkasan performa tiket, distribusi layanan, dan aktivitas civitas kampus secara menyeluruh.
                </p>
            </div>
        </div>

        <!-- Quick Action Bar -->
        <div class="flex items-center gap-2 shrink-0">
            <a href="{{ route('tickets.inbox') }}"
               class="px-3.5 py-2 rounded-xl bg-white hover:bg-slate-50 border border-slate-200 text-slate-700 text-xs font-semibold shadow-sm transition flex items-center gap-1.5">
                @include('partials.icon', ['name' => 'inbox-arrow-down', 'class' => 'w-3.5 h-3.5 text-slate-500'])
                <span>Inbox Antrean</span>
            </a>
            <button type="button"
                    @click="createTicketModal = true"
                    class="px-3.5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 active:scale-[0.99] text-white text-xs font-semibold shadow-sm shadow-indigo-600/20 transition flex items-center gap-1.5">
                @include('partials.icon', ['name' => 'plus-circle', 'class' => 'w-3.5 h-3.5'])
                <span>Buat Tiket</span>
            </button>
        </div>
    </div>

    <!-- 4 KPI Metrics Card (Clean SaaS Stat Cards) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <!-- Metric 1: Total Tiket -->
        <div class="p-5 rounded-2xl bg-white border border-slate-200/80 shadow-sm relative overflow-hidden group hover:border-indigo-200 transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Tiket</span>
                <div class="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
                    @include('partials.icon', ['name' => 'ticket', 'class' => 'w-4 h-4'])
                </div>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl font-extrabold text-slate-900">{{ number_format($stats['total_all_tickets']) }}</span>
                <span class="text-[11px] font-medium text-slate-400">tiket masuk</span>
            </div>
            <div class="mt-3 flex items-center gap-1.5 text-[11px]">
                <span class="inline-flex items-center font-semibold text-emerald-600 bg-emerald-50 px-1.5 py-0.5 rounded">
                    {{ $stats['completion_rate'] }}% Tuntas
                </span>
                <span class="text-slate-400">rasio penyelesaian</span>
            </div>
        </div>

        <!-- Metric 2: Sedang Diproses -->
        <div class="p-5 rounded-2xl bg-white border border-slate-200/80 shadow-sm relative overflow-hidden group hover:border-sky-200 transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Sedang Diproses</span>
                <div class="w-9 h-9 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center">
                    @include('partials.icon', ['name' => 'clock', 'class' => 'w-4 h-4'])
                </div>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl font-extrabold text-slate-900">{{ number_format($stats['in_progress_tickets']) }}</span>
                <span class="text-[11px] font-medium text-slate-400">aktif berjalan</span>
            </div>
            <div class="mt-3 flex items-center gap-1.5 text-[11px]">
                <span class="inline-flex items-center font-semibold text-amber-600 bg-amber-50 px-1.5 py-0.5 rounded">
                    {{ $stats['open_tickets'] }} Tiket Baru
                </span>
                <span class="text-slate-400">menunggu triase</span>
            </div>
        </div>

        <!-- Metric 3: Tiket Selesai -->
        <div class="p-5 rounded-2xl bg-white border border-slate-200/80 shadow-sm relative overflow-hidden group hover:border-emerald-200 transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Tiket Selesai</span>
                <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                    @include('partials.icon', ['name' => 'check-circle', 'class' => 'w-4 h-4'])
                </div>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl font-extrabold text-emerald-600">{{ number_format($stats['resolved_tickets']) }}</span>
                <span class="text-[11px] font-medium text-slate-400">selesai divalidasi</span>
            </div>
            <div class="mt-3 flex items-center gap-1.5 text-[11px]">
                <span class="text-slate-500">Terselesaikan tepat sasaran</span>
            </div>
        </div>

        <!-- Metric 4: Civitas Kampus -->
        <div class="p-5 rounded-2xl bg-white border border-slate-200/80 shadow-sm relative overflow-hidden group hover:border-violet-200 transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Pengguna Civitas</span>
                <div class="w-9 h-9 rounded-xl bg-violet-50 text-violet-600 flex items-center justify-center">
                    @include('partials.icon', ['name' => 'users', 'class' => 'w-4 h-4'])
                </div>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl font-extrabold text-slate-900">{{ number_format($stats['total_users']) }}</span>
                <span class="text-[11px] font-medium text-slate-400">akun terdaftar</span>
            </div>
            <div class="mt-3 flex items-center gap-1 text-[11px] text-slate-500 truncate">
                <span class="font-medium text-slate-700">{{ $stats['students_count'] }}</span> Mhs &bull;
                <span class="font-medium text-slate-700">{{ $stats['lecturers_count'] }}</span> Dosen &bull;
                <span class="font-medium text-slate-700">{{ $stats['employees_count'] }}</span> Staf
            </div>
        </div>
    </div>

    <!-- Main Grid Content (8 / 4 Column Layout) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- Kolom Kiri (8 Kolom): Tabel Tiket Terbaru Sistem & Top Kategori -->
        <div class="lg:col-span-8 space-y-6">
            <!-- Tabel Tiket Terbaru Seluruh Kampus -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-bold text-slate-900">Tiket Terbaru Seluruh Kampus</h2>
                        <p class="text-xs text-slate-500 mt-0.5">Monitoring permohonan layanan civitas lintas fakultas dan unit kerja</p>
                    </div>
                    <a href="{{ route('tickets.inbox') }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-700 flex items-center gap-1 transition">
                        <span>Lihat Semua</span>
                        @include('partials.icon', ['name' => 'arrow-right', 'class' => 'w-3.5 h-3.5'])
                    </a>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50/80 text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                                <th class="py-3 px-4">No. Tiket</th>
                                <th class="py-3 px-4">Pemohon</th>
                                <th class="py-3 px-4">Subjek & Layanan</th>
                                <th class="py-3 px-4">Prioritas</th>
                                <th class="py-3 px-4">Tahap / Status</th>
                                <th class="py-3 px-4 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs">
                            @forelse($recentSystemTickets as $t)
                                @php
                                    $pBadge = $priorityBadges[$t->priority] ?? $priorityBadges['medium'];
                                    $sBadge = $statusBadges[$t->status] ?? $statusBadges['open'];
                                @endphp
                                <tr class="hover:bg-slate-50/70 transition">
                                    <td class="py-3 px-4 font-mono font-semibold text-indigo-600 whitespace-nowrap">
                                        <a href="{{ route('tickets.show', $t) }}" class="hover:underline">
                                            {{ $t->ticket_number }}
                                        </a>
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="flex items-center gap-2">
                                            <div class="w-7 h-7 rounded-full bg-slate-100 border border-slate-200 text-slate-700 font-bold text-[10px] flex items-center justify-center shrink-0">
                                                {{ strtoupper(substr($t->creator?->name ?? 'U', 0, 2)) }}
                                            </div>
                                            <div class="min-w-0">
                                                <p class="font-medium text-slate-900 truncate">{{ $t->creator?->name ?? 'Pengguna' }}</p>
                                                <p class="text-[10px] text-slate-400 truncate">{{ $t->creatorRole?->name ?? 'Civitas' }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4 max-w-[200px]">
                                        <p class="font-semibold text-slate-900 truncate" title="{{ $t->title }}">{{ $t->title }}</p>
                                        <p class="text-[11px] text-slate-400 truncate">{{ $t->category?->name ?? 'Layanan Umum' }}</p>
                                    </td>
                                    <td class="py-3 px-4 whitespace-nowrap">
                                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-semibold border {{ $pBadge['bg'] }} {{ $pBadge['text'] }} {{ $pBadge['border'] }}">
                                            <span class="w-1.5 h-1.5 rounded-full {{ $pBadge['dot'] }}"></span>
                                            {{ $pBadge['label'] }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 whitespace-nowrap">
                                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-semibold border {{ $sBadge['bg'] }} {{ $sBadge['text'] }} {{ $sBadge['border'] }}">
                                            <span class="w-1.5 h-1.5 rounded-full {{ $sBadge['dot'] }}"></span>
                                            {{ $sBadge['label'] }}
                                        </span>
                                        <span class="block text-[10px] text-slate-400 mt-0.5">
                                            @if($t->assignedUser)
                                                Teknisi: {{ $t->assignedUser->name }}
                                            @elseif($t->currentRole)
                                                Role: {{ $t->currentRole->name }}
                                            @endif
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-right whitespace-nowrap">
                                        <a href="{{ route('tickets.show', $t) }}"
                                           class="px-2.5 py-1 rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-600 font-semibold text-[11px] transition inline-block">
                                            Buka &rarr;
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-12 text-center text-xs text-slate-400">
                                        <div class="w-10 h-10 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-2">
                                            @include('partials.icon', ['name' => 'inbox-arrow-down', 'class' => 'w-5 h-5'])
                                        </div>
                                        Belum ada tiket baru yang diajukan di sistem.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Distribusi Kategori Layanan Kampus -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="text-sm font-bold text-slate-900">Kategori Layanan Aktif</h2>
                        <p class="text-xs text-slate-500 mt-0.5">Volume tiket dan konfigurasi unit target penanganan</p>
                    </div>
                    <a href="{{ route('admin.categories') }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-700">
                        Kelola Kategori &rarr;
                    </a>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @forelse($topCategories as $cat)
                        <div class="p-3.5 rounded-xl border border-slate-200/80 hover:border-indigo-200 bg-slate-50/50 hover:bg-white transition flex items-center justify-between">
                            <div class="min-w-0 pr-2">
                                <p class="text-xs font-semibold text-slate-900 truncate">{{ $cat->name }}</p>
                                <p class="text-[10px] text-slate-500 truncate mt-0.5">
                                    Target: <span class="font-medium text-indigo-600">{{ $cat->initialTargetRole?->name ?? 'Otomatis' }}</span>
                                </p>
                            </div>
                            <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-100 shrink-0">
                                {{ $cat->tickets_count }} tiket
                            </span>
                        </div>
                    @empty
                        <p class="text-xs text-slate-400 col-span-2 py-4 text-center">Belum ada kategori layanan terdaftar.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Kolom Kanan (4 Kolom): Distribusi Status, Live Activity Log, Pintasan Manajemen -->
        <div class="lg:col-span-4 space-y-6">
            <!-- Card 1: Distribusi Status Tiket -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5">
                <h2 class="text-sm font-bold text-slate-900 mb-1">Distribusi Status Tiket</h2>
                <p class="text-xs text-slate-500 mb-4">Persentase tiket berdasarkan tahap operasional</p>

                @php
                    $total = max(1, $stats['total_all_tickets']);
                    $openPct = round(($stats['open_tickets'] / $total) * 100);
                    $progressPct = round(($stats['in_progress_tickets'] / $total) * 100);
                    $resolvedPct = round(($stats['resolved_tickets'] / $total) * 100);
                    $rejectedPct = round(($stats['rejected_tickets'] / $total) * 100);
                @endphp

                <div class="space-y-3.5">
                    <!-- Open -->
                    <div>
                        <div class="flex items-center justify-between text-xs mb-1">
                            <span class="flex items-center gap-1.5 font-medium text-slate-700">
                                <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                                Baru Masuk (Open)
                            </span>
                            <span class="font-semibold text-slate-900">{{ $stats['open_tickets'] }} ({{ $openPct }}%)</span>
                        </div>
                        <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                            <div class="bg-amber-500 h-2 rounded-full" style="width: {{ $openPct }}%"></div>
                        </div>
                    </div>

                    <!-- In Progress -->
                    <div>
                        <div class="flex items-center justify-between text-xs mb-1">
                            <span class="flex items-center gap-1.5 font-medium text-slate-700">
                                <span class="w-2 h-2 rounded-full bg-sky-500"></span>
                                Sedang Diproses
                            </span>
                            <span class="font-semibold text-slate-900">{{ $stats['in_progress_tickets'] }} ({{ $progressPct }}%)</span>
                        </div>
                        <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                            <div class="bg-sky-500 h-2 rounded-full" style="width: {{ $progressPct }}%"></div>
                        </div>
                    </div>

                    <!-- Resolved -->
                    <div>
                        <div class="flex items-center justify-between text-xs mb-1">
                            <span class="flex items-center gap-1.5 font-medium text-slate-700">
                                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                Selesai Tuntas
                            </span>
                            <span class="font-semibold text-slate-900">{{ $stats['resolved_tickets'] }} ({{ $resolvedPct }}%)</span>
                        </div>
                        <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                            <div class="bg-emerald-500 h-2 rounded-full" style="width: {{ $resolvedPct }}%"></div>
                        </div>
                    </div>

                    <!-- Rejected -->
                    <div>
                        <div class="flex items-center justify-between text-xs mb-1">
                            <span class="flex items-center gap-1.5 font-medium text-slate-700">
                                <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                                Ditolak
                            </span>
                            <span class="font-semibold text-slate-900">{{ $stats['rejected_tickets'] }} ({{ $rejectedPct }}%)</span>
                        </div>
                        <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                            <div class="bg-rose-500 h-2 rounded-full" style="width: {{ $rejectedPct }}%"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card 2: Aktivitas Terkini Sistem (Live System Activity) -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5">
                <div class="flex items-center justify-between mb-3.5">
                    <h2 class="text-sm font-bold text-slate-900">Aktivitas Terkini Sistem</h2>
                    <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Live Log</span>
                </div>

                <div class="space-y-3">
                    @forelse($recentLogs as $log)
                        @php
                            $badge = $log->actionBadge();
                        @endphp
                        <div class="flex items-start gap-2.5 text-xs pb-3 border-b border-slate-100 last:border-b-0 last:pb-0">
                            <div class="w-6 h-6 rounded-full bg-slate-100 text-slate-700 font-bold text-[9px] flex items-center justify-center shrink-0 mt-0.5">
                                {{ strtoupper(substr($log->user?->name ?? 'S', 0, 2)) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-1">
                                    <p class="font-semibold text-slate-900 truncate">{{ $log->user?->name ?? 'Sistem' }}</p>
                                    <span class="text-[10px] text-slate-400 shrink-0">{{ $log->created_at->diffForHumans(null, true) }}</span>
                                </div>
                                <p class="text-[11px] text-slate-600 line-clamp-1 mt-0.5">{{ $log->note ?: 'Melakukan pembaruan tiket' }}</p>
                                @if($log->ticket)
                                    <a href="{{ route('tickets.show', $log->ticket) }}" class="text-[10px] font-mono text-indigo-600 hover:underline">
                                        {{ $log->ticket->ticket_number }}
                                    </a>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-slate-400 py-4 text-center">Belum ada catatan aktivitas tiket.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

@else
    {{-- ============================================================== --}}
    {{-- DASHBOARD ROLE CIVITAS (MAHASISWA, DOSEN, KAPRODI, STAFF, DLL) --}}
    {{-- ============================================================== --}}

    <!-- Header Dashboard Role -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6 bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-lg font-bold text-slate-900 tracking-tight">Beranda &bull; Role {{ $role?->name ?? 'Civitas Kampus' }}</h1>
                <span class="text-[11px] font-semibold px-2.5 py-0.5 rounded-full bg-indigo-50 text-indigo-700 border border-indigo-200/80">
                    {{ $user->name }}
                </span>
            </div>
            <p class="text-xs text-slate-500 mt-1">
                Ringkasan tiket masuk antrean Anda, progres persetujuan berjenjang, dan riwayat permohonan.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <button type="button"
                    @click="createTicketModal = true"
                    class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-sm shadow-indigo-600/20 transition flex items-center gap-1.5">
                @include('partials.icon', ['name' => 'plus-circle', 'class' => 'w-3.5 h-3.5'])
                <span>Buat Tiket Baru</span>
            </button>
        </div>
    </div>

    <!-- 4 Metrik Role -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="p-4 rounded-2xl bg-white border border-slate-200/80 shadow-sm">
            <p class="text-xs text-slate-500">Tiket Masuk Antrean</p>
            <p class="text-2xl font-bold text-slate-900 mt-1">{{ $stats['role_inbox_active'] }}</p>
            <p class="text-[10px] text-slate-400 mt-1">Perlu tindakan Anda</p>
        </div>
        <div class="p-4 rounded-2xl bg-white border border-slate-200/80 shadow-sm">
            <p class="text-xs text-slate-500">Diteruskan / Approval</p>
            <p class="text-2xl font-bold text-slate-900 mt-1">{{ $stats['escalated_or_forwarded'] }}</p>
            <p class="text-[10px] text-slate-400 mt-1">Ke tahap atasan</p>
        </div>
        <div class="p-4 rounded-2xl bg-white border border-slate-200/80 shadow-sm">
            <p class="text-xs text-slate-500">Total Tiket Saya</p>
            <p class="text-2xl font-bold text-slate-900 mt-1">{{ $stats['my_tickets_total'] }}</p>
            <p class="text-[10px] text-slate-400 mt-1">Permohonan diajukan</p>
        </div>
        <div class="p-4 rounded-2xl bg-white border border-slate-200/80 shadow-sm">
            <p class="text-xs text-slate-500">Sudah Di-Approve</p>
            <p class="text-2xl font-bold text-emerald-600 mt-1">{{ $stats['my_tickets_resolved'] }}</p>
            <p class="text-[10px] text-emerald-500 mt-1">Tuntas disetujui</p>
        </div>
    </div>

    <!-- Space-Optimized Tables (Full Width Clean Layout, Zero Clutter) -->
    <div class="space-y-6">
        @if($role?->can_receive_ticket)
            <!-- Tabel Tiket Masuk Role -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-bold text-slate-900">Tiket yang Perlu Tindakan Anda</h2>
                        <p class="text-xs text-slate-500 mt-0.5">Permohonan yang memerlukan review, persetujuan, atau tindak lanjut dari Anda</p>
                    </div>
                    <a href="{{ route('tickets.inbox') }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-700 flex items-center gap-1">
                        <span>Buka Inbox Lengkap</span>
                        @include('partials.icon', ['name' => 'arrow-right', 'class' => 'w-3.5 h-3.5'])
                    </a>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50/80 text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                                <th class="py-3 px-4">No. Tiket</th>
                                <th class="py-3 px-4">Pemohon</th>
                                <th class="py-3 px-4">Judul Tiket</th>
                                <th class="py-3 px-4">Tahap Proses</th>
                                <th class="py-3 px-4">Status</th>
                                <th class="py-3 px-4 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs">
                            @forelse($incomingTickets as $t)
                                @php
                                    $sBadge = $statusBadges[$t->status] ?? $statusBadges['open'];
                                @endphp
                                <tr class="hover:bg-slate-50/70 transition">
                                    <td class="py-3 px-4 font-mono font-semibold text-indigo-600 whitespace-nowrap">
                                        <a href="{{ route('tickets.show', $t) }}" class="hover:underline">{{ $t->ticket_number }}</a>
                                    </td>
                                    <td class="py-3 px-4">
                                        <span class="font-medium text-slate-800 block">{{ $t->creator?->name }}</span>
                                        <span class="text-[11px] text-slate-400">{{ $t->creatorRole?->name }}</span>
                                    </td>
                                    <td class="py-3 px-4">
                                        <span class="font-semibold text-slate-900 block">{{ $t->title }}</span>
                                        <span class="text-[11px] text-slate-400">{{ $t->category?->name }}</span>
                                    </td>
                                    <td class="py-3 px-4 whitespace-nowrap">
                                        <span class="text-[11px] text-slate-600">Tahap {{ $t->approval_step }} ({{ $t->currentRole?->name }})</span>
                                    </td>
                                    <td class="py-3 px-4 whitespace-nowrap">
                                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-semibold border {{ $sBadge['bg'] }} {{ $sBadge['text'] }} {{ $sBadge['border'] }}">
                                            <span class="w-1.5 h-1.5 rounded-full {{ $sBadge['dot'] }}"></span>
                                            {{ $sBadge['label'] }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-right whitespace-nowrap">
                                        <a href="{{ route('tickets.show', $t) }}"
                                           class="px-2.5 py-1 rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-600 font-semibold text-[11px] transition inline-block">
                                            Proses &rarr;
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-10 text-center text-xs text-slate-400">
                                        Belum ada tiket masuk di antrean Role {{ $role?->name }}.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <!-- Tabel Riwayat Tiket Saya -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-bold text-slate-900">Tiket Terbaru Saya</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Pantau status pengerjaan dan validasi permohonan yang Anda ajukan</p>
                </div>
                <div class="flex items-center gap-3">
                    <a href="{{ route('tickets.my') }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-700">Daftar Tiket Saya &rarr;</a>
                    <a href="{{ route('tickets.history') }}" class="text-xs font-semibold text-emerald-600 hover:text-emerald-700">Riwayat Selesai &rarr;</a>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50/80 text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                            <th class="py-3 px-4">No. Tiket</th>
                            <th class="py-3 px-4">Judul & Kategori</th>
                            <th class="py-3 px-4">Posisi Penanganan</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4">Dokumen</th>
                            <th class="py-3 px-4 text-right">Detail</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs">
                        @forelse($myCreatedTickets as $mt)
                            @php
                                $sBadge = $statusBadges[$mt->status] ?? $statusBadges['open'];
                            @endphp
                            <tr class="hover:bg-slate-50/70 transition">
                                <td class="py-3 px-4 font-mono font-semibold text-indigo-600 whitespace-nowrap">
                                    <a href="{{ route('tickets.show', $mt) }}" class="hover:underline">{{ $mt->ticket_number }}</a>
                                </td>
                                <td class="py-3 px-4">
                                    <a href="{{ route('tickets.show', $mt) }}" class="font-semibold text-slate-900 hover:text-indigo-600 block truncate max-w-xs">
                                        {{ $mt->title }}
                                    </a>
                                    <span class="text-[11px] text-slate-400">{{ $mt->category?->name }}</span>
                                </td>
                                <td class="py-3 px-4 whitespace-nowrap">
                                    <span class="text-[11px] text-slate-600">
                                        Tahap {{ $mt->approval_step }}: <strong>Role {{ $mt->currentRole?->name }}</strong>
                                    </span>
                                </td>
                                <td class="py-3 px-4 whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-semibold border {{ $sBadge['bg'] }} {{ $sBadge['text'] }} {{ $sBadge['border'] }}">
                                        <span class="w-1.5 h-1.5 rounded-full {{ $sBadge['dot'] }}"></span>
                                        {{ $sBadge['label'] }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 whitespace-nowrap">
                                    @if($mt->attachment_path)
                                        <button type="button"
                                                @click="openDocPreview('{{ route('tickets.preview', $mt) }}', '{{ route('tickets.attachment', $mt) }}', '{{ addslashes($mt->attachment_name ?? 'Dokumen') }}')"
                                                class="text-[11px] font-semibold text-indigo-600 hover:underline inline-flex items-center gap-1">
                                            <span>📎 Lampiran</span>
                                        </button>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="py-3 px-4 text-right whitespace-nowrap">
                                    <a href="{{ route('tickets.show', $mt) }}" class="text-xs font-semibold text-indigo-600 hover:underline">
                                        Lihat &rarr;
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-10 text-center text-xs text-slate-400">Belum ada tiket yang pernah diajukan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endif

@endsection
