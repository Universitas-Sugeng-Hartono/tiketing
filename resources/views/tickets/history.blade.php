@extends('layouts.app')

@section('content')
<!-- Header Halaman Riwayat Tiket (Khusus Status Approved / Resolved) -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
        <h1 class="text-lg font-bold text-slate-900">Riwayat Tiket (Sudah Selesai)</h1>
        <p class="text-xs text-slate-500">
            Tiket selesai pada divisi Anda, yang Anda ajukan, atau yang diselesaikan pada role Anda.
        </p>
    </div>

    <a href="{{ route('tickets.my') }}"
       class="px-3.5 py-2 rounded-lg bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-semibold transition self-start sm:self-auto">
        ← Kembali ke Tiket Saya
    </a>
</div>

<!-- Card Utama: Toolbar Search/Dropdown + Tabel Riwayat Approve -->
<div class="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-2xs">
    <!-- Toolbar Pencarian & Dropdown Kategori -->
    <form action="{{ route('tickets.history') }}" method="GET" class="p-4 border-b border-slate-100 bg-slate-50/50 space-y-3">
        <div class="flex flex-col lg:flex-row items-stretch lg:items-center gap-2">
            <div class="relative flex-1 max-w-md">
                <svg class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                </svg>
                <input type="text" name="search" value="{{ $search }}"
                       placeholder="Cari No. Tiket atau Judul..."
                       class="w-full pl-9 pr-3 py-2 rounded-lg bg-white border border-slate-200 text-xs focus:outline-none focus:border-slate-900">
            </div>

            <input type="text" name="requester" value="{{ $requester ?? '' }}"
                   placeholder="Nama pemohon..."
                   class="px-3 py-2 rounded-lg bg-white border border-slate-200 text-xs text-slate-700 focus:outline-none focus:border-slate-900">

            <select name="status" class="px-3 py-2 rounded-lg bg-white border border-slate-200 text-xs text-slate-700 focus:outline-none focus:border-slate-900">
                <option value="resolved" {{ ($status ?? 'resolved') === 'resolved' ? 'selected' : '' }}>Selesai / Final</option>
                <option value="rejected" {{ ($status ?? '') === 'rejected' ? 'selected' : '' }}>Ditolak</option>
                <option value="closed" {{ ($status ?? '') === 'closed' ? 'selected' : '' }}>Ditutup</option>
                <option value="all" {{ ($status ?? '') === 'all' ? 'selected' : '' }}>Semua status akhir</option>
            </select>

            <select name="category_id" class="px-3 py-2 rounded-lg bg-white border border-slate-200 text-xs text-slate-700 focus:outline-none focus:border-slate-900">
                <option value="">Semua Kategori</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" {{ (string) $categoryId === (string) $cat->id ? 'selected' : '' }}>
                        {{ $cat->name }}
                    </option>
                @endforeach
            </select>

            <input type="date" name="date_from" value="{{ $dateFrom ?? '' }}"
                   class="px-3 py-2 rounded-lg bg-white border border-slate-200 text-xs text-slate-700 focus:outline-none focus:border-slate-900"
                   title="Dari tanggal">
            <input type="date" name="date_to" value="{{ $dateTo ?? '' }}"
                   class="px-3 py-2 rounded-lg bg-white border border-slate-200 text-xs text-slate-700 focus:outline-none focus:border-slate-900"
                   title="Sampai tanggal">

            <select name="per_page" class="px-3 py-2 rounded-lg bg-white border border-slate-200 text-xs text-slate-700 focus:outline-none focus:border-slate-900">
                <option value="25" {{ (int) ($perPage ?? 25) === 25 ? 'selected' : '' }}>25 / halaman</option>
                <option value="50" {{ (int) ($perPage ?? 25) === 50 ? 'selected' : '' }}>50 / halaman</option>
                <option value="100" {{ (int) ($perPage ?? 25) === 100 ? 'selected' : '' }}>100 / halaman</option>
            </select>

            <button type="submit" class="px-3.5 py-2 rounded-lg bg-slate-900 hover:bg-slate-800 text-white text-xs font-medium transition">
                Filter
            </button>

            @if($search || ($requester ?? '') !== '' || $categoryId || ($status ?? 'resolved') !== 'resolved' || ($dateFrom ?? null) || ($dateTo ?? null) || (int) ($perPage ?? 25) !== 25)
                <a href="{{ route('tickets.history') }}"
                   class="px-3 py-2 rounded-lg bg-white border border-slate-200 text-xs text-slate-600 hover:bg-slate-100">
                    Reset
                </a>
            @endif
        </div>
        <p class="text-[11px] text-slate-400">Status Final = tiket selesai ditutup. Filter "Semua status akhir" juga menampilkan ditolak/ditutup.</p>
    </form>

    <!-- Tabel Riwayat Tiket yang Sudah Di-Approve -->
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="border-b border-slate-200 bg-slate-50/80 text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                    <th class="py-3 px-4 w-44">No. Tiket</th>
                    <th class="py-3 px-4">Pemohon</th>
                    <th class="py-3 px-4">Judul & Kategori Tiket</th>
                    <th class="py-3 px-4">Proses Approval (Hirarki)</th>
                    <th class="py-3 px-4 w-36">Status Akhir</th>
                    <th class="py-3 px-4 w-40">Dokumen Lampiran</th>
                    <th class="py-3 px-4 w-24 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-xs">
                @forelse($tickets as $ticket)
                    @php
                        $stepper = $ticket->getHierarchyStepper();
                        $lastApprovalLog = $ticket->logs->whereIn('action', ['approved_step', 'final_approved', 'resolved'])->last();
                    @endphp
                    <tr class="hover:bg-slate-50/70 transition align-top">
                        <!-- 1. No. Tiket -->
                        <td class="py-3.5 px-4">
                            <span class="font-mono font-semibold text-emerald-700 block">{{ $ticket->ticket_number }}</span>
                            @include('tickets.partials.dates', ['ticket' => $ticket, 'format' => 'd M Y'])
                            <span class="text-[11px] text-slate-400">
                                Selesai {{ ($ticket->resolved_at ?? $ticket->updated_at)->format('d M Y') }}
                            </span>
                        </td>

                        <td class="py-3.5 px-4">
                            <span class="font-medium text-slate-800 block">{{ $ticket->creator?->name ?? '—' }}</span>
                            <span class="text-[11px] text-slate-400">{{ $ticket->creatorRole?->name }}</span>
                        </td>

                        <!-- 2. Judul & Kategori -->
                        <td class="py-3.5 px-4">
                            <a href="{{ route('tickets.show', $ticket) }}" class="font-semibold text-slate-900 hover:text-indigo-600 block">
                                {{ $ticket->title }}
                            </a>
                            <span class="text-[11px] text-slate-500 block mt-0.5">{{ $ticket->category?->name }}</span>
                        </td>

                        <!-- 3. Proses Approval (Hirarki) -->
                        <td class="py-3.5 px-4">
                            <div class="flex items-center gap-1 flex-wrap mb-1">
                                @foreach($stepper as $idx => $step)
                                    @if($idx > 0)
                                        <span class="text-slate-300 text-[10px]">→</span>
                                    @endif
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        ✓ {{ $step['role']->name }}
                                    </span>
                                @endforeach
                            </div>
                            <p class="text-[11px] text-emerald-700 font-medium">
                                Disetujui tuntas di Role {{ $ticket->currentRole?->name }}
                                @if($lastApprovalLog && $lastApprovalLog->user)
                                    oleh {{ $lastApprovalLog->user->name }}
                                @endif
                            </p>
                        </td>

                        <!-- 4. Status Akhir -->
                        <td class="py-3.5 px-4 whitespace-nowrap">
                            @php
                                $historyBadge = match ($ticket->status) {
                                    'rejected' => ['bg-rose-50 text-rose-700 border-rose-200', 'Ditolak'],
                                    'closed' => ['bg-slate-100 text-slate-700 border-slate-200', 'Ditutup'],
                                    default => ['bg-emerald-50 text-emerald-700 border-emerald-200', '✓ Final / Selesai'],
                                };
                            @endphp
                            <span class="inline-block text-[11px] font-medium px-2.5 py-0.5 rounded-md border {{ $historyBadge[0] }}">
                                {{ $historyBadge[1] }}
                            </span>
                        </td>

                        <!-- 5. Dokumen Lampiran (Preview Modal) -->
                        <td class="py-3.5 px-4 whitespace-nowrap">
                            @if($ticket->attachment_path)
                                <button type="button"
                                        @click="openDocPreview('{{ route('tickets.preview', $ticket) }}', '{{ route('tickets.attachment', $ticket) }}', '{{ addslashes($ticket->attachment_name ?? 'Dokumen Lampiran') }}')"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 text-[11px] font-semibold transition">
                                    <span>📎 Preview Dokumen</span>
                                </button>
                            @else
                                <span class="text-slate-400 text-[11px]">Tidak ada lampiran</span>
                            @endif
                        </td>

                        <!-- 6. Aksi -->
                        <td class="py-3.5 px-4 text-right whitespace-nowrap">
                            <div class="inline-flex items-center gap-1.5">
                                @if($ticket->canBeRevisedBy($user))
                                    <a href="{{ route('tickets.show', $ticket) }}#revisi"
                                       class="inline-block px-3 py-1 rounded-lg bg-slate-900 hover:bg-slate-800 text-white font-medium transition">
                                        Edit
                                    </a>
                                @endif
                                <a href="{{ route('tickets.show', $ticket) }}"
                                   class="inline-block px-3 py-1 rounded-lg border border-slate-200 hover:bg-slate-100 text-slate-700 font-medium transition">
                                    Detail
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="py-10 px-4 text-center text-xs text-slate-400">
                            Tidak ada riwayat tiket untuk filter ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if(method_exists($tickets, 'links'))
        <div class="px-4 py-3 border-t border-slate-100 bg-slate-50/40">
            {{ $tickets->links() }}
        </div>
    @endif
</div>
@endsection
