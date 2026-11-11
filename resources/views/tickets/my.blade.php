@extends('layouts.app')

@section('content')
@php
    $statusColors = [
        'open' => 'bg-amber-50 text-amber-700 border-amber-200',
        'in_progress' => 'bg-sky-50 text-sky-700 border-sky-200',
        'escalated' => 'bg-violet-50 text-violet-700 border-violet-200',
        'waiting_next_approval' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
    ];
    $prioColors = [
        'low' => 'bg-slate-100 text-slate-600',
        'medium' => 'bg-blue-50 text-blue-700',
        'high' => 'bg-orange-50 text-orange-700',
        'urgent' => 'bg-rose-50 text-rose-700 font-semibold',
    ];
@endphp

<!-- Header Halaman Tiket Saya + Tombol Buat Tiket Baru -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
        <h1 class="text-lg font-bold text-slate-900">Tiket Saya (Sedang Diproses)</h1>
        <p class="text-xs text-slate-500">
            Daftar pengajuan tiket Anda yang sedang berjalan di alur hirarki role beserta tahapan prosesnya.
        </p>
    </div>

    <div class="flex items-center gap-2">
        <a href="{{ route('tickets.history') }}"
           class="px-3.5 py-2 rounded-lg bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-semibold transition">
            Lihat Riwayat Approve →
        </a>
        <button type="button" @click="createTicketModal = true"
                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-slate-900 hover:bg-slate-800 text-white text-xs font-semibold transition shadow-2xs cursor-pointer">
            <span>+ Buat Tiket Baru</span>
        </button>
    </div>
</div>

<!-- Card Utama: Filter Rapi + Tabel Tiket Saya yang Sedang Diproses -->
<div class="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-2xs">
    <!-- Toolbar Pencarian & Dropdown Kategori -->
    <form action="{{ route('tickets.my') }}" method="GET" class="p-4 border-b border-slate-100 bg-slate-50/50 flex flex-col lg:flex-row items-stretch lg:items-center gap-2">
        <div class="relative flex-1 max-w-md">
            <svg class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
            </svg>
            <input type="text" name="search" value="{{ $search }}"
                   placeholder="Cari No. Tiket atau Judul Tiket..."
                   class="w-full pl-9 pr-3 py-2 rounded-lg bg-white border border-slate-200 text-xs focus:outline-none focus:border-slate-900">
        </div>

        <select name="status" class="px-3 py-2 rounded-lg bg-white border border-slate-200 text-xs text-slate-700 focus:outline-none focus:border-slate-900">
            <option value="">Semua status proses</option>
            <option value="open" {{ ($status ?? '') === 'open' ? 'selected' : '' }}>Open</option>
            <option value="in_progress" {{ ($status ?? '') === 'in_progress' ? 'selected' : '' }}>Diproses</option>
            <option value="escalated" {{ ($status ?? '') === 'escalated' ? 'selected' : '' }}>Dieskalasi</option>
            <option value="waiting_next_approval" {{ ($status ?? '') === 'waiting_next_approval' ? 'selected' : '' }}>Menunggu approval</option>
        </select>

        <select name="category_id" class="px-3 py-2 rounded-lg bg-white border border-slate-200 text-xs text-slate-700 focus:outline-none focus:border-slate-900">
            <option value="">Semua Kategori Layanan</option>
            @foreach($categories as $cat)
                <option value="{{ $cat->id }}" {{ (string) $categoryId === (string) $cat->id ? 'selected' : '' }}>
                    {{ $cat->name }}
                </option>
            @endforeach
        </select>

        <select name="per_page" class="px-3 py-2 rounded-lg bg-white border border-slate-200 text-xs text-slate-700 focus:outline-none focus:border-slate-900">
            <option value="25" {{ (int) ($perPage ?? 25) === 25 ? 'selected' : '' }}>25 / halaman</option>
            <option value="50" {{ (int) ($perPage ?? 25) === 50 ? 'selected' : '' }}>50 / halaman</option>
            <option value="100" {{ (int) ($perPage ?? 25) === 100 ? 'selected' : '' }}>100 / halaman</option>
        </select>

        <button type="submit" class="px-3.5 py-2 rounded-lg bg-slate-900 hover:bg-slate-800 text-white text-xs font-medium transition">
            Filter
        </button>

        @if($search || $categoryId || ($status ?? null) || (int) ($perPage ?? 25) !== 25)
            <a href="{{ route('tickets.my') }}"
               class="px-3 py-2 rounded-lg bg-white border border-slate-200 text-xs text-slate-600 hover:bg-slate-100">
                Reset
            </a>
        @endif
    </form>

    <!-- Tabel Tiket Saya (Sedang Diproses) -->
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="border-b border-slate-200 bg-slate-50/80 text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                    <th class="py-3 px-4 w-44">No. Tiket</th>
                    <th class="py-3 px-4">Judul & Kategori Tiket</th>
                    <th class="py-3 px-4">Proses (Tahap Hirarki)</th>
                    <th class="py-3 px-4 w-32">Prioritas</th>
                    <th class="py-3 px-4 w-36">Progres</th>
                    <th class="py-3 px-4 w-36">Status</th>
                    <th class="py-3 px-4 w-36">Dokumen</th>
                    <th class="py-3 px-4 w-24 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-xs">
                @forelse($tickets as $ticket)
                    @php
                        $stepper = $ticket->getHierarchyStepper();
                    @endphp
                    <tr class="hover:bg-slate-50/70 transition align-top">
                        <!-- 1. No. Tiket -->
                        <td class="py-3.5 px-4">
                            <span class="font-mono font-semibold text-indigo-600 block">{{ $ticket->ticket_number }}</span>
                            @include('tickets.partials.dates', ['ticket' => $ticket, 'format' => 'd M Y'])
                        </td>

                        <!-- 2. Judul & Kategori -->
                        <td class="py-3.5 px-4">
                            <a href="{{ route('tickets.show', $ticket) }}" class="font-semibold text-slate-900 hover:text-indigo-600 block">
                                {{ $ticket->title }}
                            </a>
                            <span class="text-[11px] text-slate-500 block mt-0.5">{{ $ticket->categoryDisplayName() }}</span>
                        </td>

                        <!-- 3. Proses (Tahap Hirarki Saat Ini) -->
                        <td class="py-3.5 px-4">
                            <div class="flex items-center gap-1 flex-wrap mb-1">
                                @foreach($stepper as $idx => $step)
                                    @if($idx > 0)
                                        <span class="text-slate-300 text-[10px]">→</span>
                                    @endif
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-medium {{ $step['state'] === 'active' ? 'bg-slate-900 text-white' : ($step['state'] === 'completed' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-400') }}">
                                        {{ $step['role']->name }}
                                    </span>
                                @endforeach
                            </div>
                            <p class="text-[11px] text-slate-500">
                                Tahap {{ $ticket->approval_step }}: Sedang diproses di <strong class="text-slate-700">Role {{ $ticket->currentRole?->name }}</strong>
                            </p>
                        </td>

                        <!-- 4. Prioritas -->
                        <td class="py-3.5 px-4 whitespace-nowrap">
                            @if($ticket->priority)
                                <span class="inline-block text-[11px] px-2 py-0.5 rounded {{ $prioColors[$ticket->priority] ?? 'bg-slate-100' }}">
                                    {{ $ticket->priorityLabel() }}
                                </span>
                            @else
                                <span class="text-[11px] text-slate-400">Menunggu Role</span>
                            @endif
                        </td>

                        <td class="py-3.5 px-4 whitespace-nowrap">
                            @include('tickets.partials.progress-meter', ['ticket' => $ticket])
                        </td>

                        <!-- 5. Status -->
                        <td class="py-3.5 px-4 whitespace-nowrap">
                            <span class="inline-block text-[11px] font-medium px-2.5 py-0.5 rounded-md border {{ $statusColors[$ticket->status] ?? 'bg-slate-100' }}">
                                {{ $ticket->statusLabel() }}
                            </span>
                        </td>

                        <!-- 6. Dokumen -->
                        <td class="py-3.5 px-4 whitespace-nowrap">
                            @if($ticket->attachment_path)
                                <button type="button"
                                        @click="openDocPreview('{{ route('tickets.preview', $ticket) }}', '{{ route('tickets.attachment', $ticket) }}', '{{ addslashes($ticket->attachment_name ?? 'Dokumen Lampiran') }}')"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 text-[11px] font-semibold transition">
                                    <span>📎 Preview Dokumen</span>
                                </button>
                            @else
                                <span class="text-slate-400 text-[11px]">—</span>
                            @endif
                        </td>

                        <!-- 7. Aksi -->
                        <td class="py-3.5 px-4 text-right whitespace-nowrap">
                            <a href="{{ route('tickets.show', $ticket) }}"
                               class="inline-block px-3 py-1 rounded-lg border border-slate-200 hover:bg-slate-100 text-slate-700 font-medium transition">
                                Detail
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="py-10 px-4 text-center text-xs text-slate-400">
                            Tidak ada tiket yang sedang diproses saat ini. Klik tombol <strong>+ Buat Tiket Baru</strong> di kanan atas untuk mengajukan tiket.
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
