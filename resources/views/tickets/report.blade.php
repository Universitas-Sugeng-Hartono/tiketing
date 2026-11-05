@extends('layouts.app')

@section('content')
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
        <h1 class="text-lg font-bold text-slate-900">Laporan Bulanan — {{ $role?->name }}</h1>
        <p class="text-xs text-slate-500">Performa staff, progres, dan status pengerjaan pada {{ $monthLabel }}.</p>
    </div>
    @if($allowed)
        <div class="flex items-center gap-2 self-start">
            <form method="GET" action="{{ route('tickets.report') }}" class="flex items-center gap-2">
                <a href="{{ route('tickets.report', ['month' => $prevMonth]) }}" class="px-2.5 py-1.5 rounded-lg border border-slate-200 text-xs text-slate-600 hover:bg-slate-50">←</a>
                <input type="month" name="month" value="{{ $month }}" onchange="this.form.submit()"
                       class="px-3 py-1.5 rounded-lg border border-slate-200 text-xs bg-white">
                <a href="{{ route('tickets.report', ['month' => $nextMonth]) }}" class="px-2.5 py-1.5 rounded-lg border border-slate-200 text-xs text-slate-600 hover:bg-slate-50">→</a>
            </form>
            <a href="{{ route('tickets.report.export', ['month' => $month]) }}"
               class="px-3 py-1.5 rounded-lg bg-slate-900 text-white text-xs font-semibold hover:bg-slate-800">
                Ekspor
            </a>
        </div>
    @endif
</div>

@if(!$allowed)
    <div class="bg-white rounded-xl border border-slate-200 px-5 py-8 text-sm text-slate-600">
        Laporan bulanan hanya untuk Kepala/SPV divisi atau kepala role.
    </div>
@else
    <div class="mb-4 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-xs text-sky-900">
        <p class="font-semibold mb-1">Cara baca rekap bulan ini</p>
        <ul class="list-disc pl-4 space-y-0.5 text-sky-800/90">
            <li><strong>Finished</strong> = staff menandai pekerjaan selesai (belum tentu tiket ditutup final).</li>
            <li><strong>Selesai ditutup (Final)</strong> = tiket di-resolve/closed; masuk riwayat final.</li>
            <li><strong>In progress / Delivered / Blocked</strong> = masih aktif di bulan ini, tidak hilang hanya karena ada yang Final.</li>
            <li>Kartu di atas memecah status terpisah — data non-final tetap dihitung di kolomnya sendiri.</li>
        </ul>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
        @foreach([
            ['Tiket', $summary['tickets'], 'text-slate-900'],
            ['Rata-rata progres', $summary['percent'] === null ? '—' : $summary['percent'].'%', 'text-emerald-700'],
            ['In progress', $summary['in_progress'], 'text-sky-700'],
            ['Blocked', $summary['blocked'], 'text-rose-700'],
            ['Delivered', $summary['delivered'], 'text-indigo-700'],
            ['Finished', $summary['finished'], 'text-emerald-700'],
            ['Selesai ditutup (Final)', $summary['resolved'], 'text-slate-700'],
            ['Ditolak', $summary['rejected'], 'text-rose-700'],
        ] as [$label, $value, $color])
            <div class="bg-white rounded-xl border border-slate-200 px-4 py-3">
                <p class="text-[11px] text-slate-500">{{ $label }}</p>
                <p class="mt-1 text-lg font-bold {{ $color }}">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden mb-5">
        <div class="px-5 py-3 border-b border-slate-100 flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-sm font-bold text-slate-900">Diagram performa staff</h2>
            <div class="flex flex-wrap gap-3 text-[10px] text-slate-500">
                <span class="inline-flex items-center gap-1"><span class="h-2 w-2 bg-indigo-500"></span> Delivered</span>
                <span class="inline-flex items-center gap-1"><span class="h-2 w-2 bg-sky-500"></span> In progress</span>
                <span class="inline-flex items-center gap-1"><span class="h-2 w-2 bg-rose-500"></span> Blocked</span>
                <span class="inline-flex items-center gap-1"><span class="h-2 w-2 bg-emerald-500"></span> Finished</span>
            </div>
        </div>
        @if($staffRows->isEmpty())
            <p class="px-5 py-8 text-center text-xs text-slate-400">Belum ada pekerjaan pada bulan ini.</p>
        @else
            <div class="px-5 py-5 overflow-x-auto">
                <div class="flex gap-2" style="min-width: {{ max(280, $staffRows->count() * 88) }}px">
                    <div class="relative h-52 w-10 shrink-0 text-[10px] text-slate-400">
                        @foreach([100, 75, 50, 25, 0] as $tick)
                            <span class="absolute right-0 -translate-y-1/2" style="top: {{ 100 - $tick }}%">{{ $tick }}%</span>
                        @endforeach
                    </div>
                    <div class="relative h-52 flex-1 border-l border-b border-slate-800">
                        @foreach([25, 50, 75, 100] as $line)
                            <div class="absolute inset-x-0 border-t border-slate-100" style="bottom: {{ $line }}%"></div>
                        @endforeach
                        <div class="absolute inset-0 flex items-end">
                            @foreach($staffRows as $row)
                                <div class="flex-1 h-full flex items-end justify-center gap-1 px-1">
                                    @foreach($row['items'] as $item)
                                        @php
                                            $barClass = match ($item['status']) {
                                                'blocked' => 'bg-rose-500',
                                                'in_progress' => 'bg-sky-500',
                                                'finished' => 'bg-emerald-500',
                                                'delivered' => 'bg-indigo-500',
                                                default => 'bg-slate-300',
                                            };
                                        @endphp
                                        <div class="w-3.5 {{ $barClass }}"
                                             style="height: {{ $item['percent'] }}%; min-height: {{ $item['percent'] > 0 ? '0' : '2px' }}"
                                             title="{{ $item['number'] }} · {{ $item['title'] }} · {{ $item['percent'] }}%"></div>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="mt-2 flex gap-2" style="min-width: {{ max(280, $staffRows->count() * 88) }}px">
                    <div class="w-10 shrink-0"></div>
                    <div class="flex-1 flex">
                        @foreach($staffRows as $row)
                            <div class="flex-1 px-1 text-center">
                                <p class="text-[11px] font-semibold text-slate-900 leading-tight">{{ $row['name'] }}</p>
                                <p class="text-[10px] text-emerald-700 mt-0.5">{{ $row['percent'] === null ? '—' : $row['percent'].'%' }}</p>
                                <p class="text-[10px] text-slate-400">{{ $row['tickets'] }} tiket</p>
                                @if($row['blocked'] > 0)
                                    <p class="text-[10px] font-semibold text-rose-700">Blocked {{ $row['blocked'] }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
                <p class="mt-3 text-[11px] text-slate-400">Satu batang adalah satu tiket. Delivered hanya untuk tiket yang baru diarahkan dan belum dikerjakan. Setelah staff mulai, status bulan ini menjadi In progress atau Blocked. Finished tercatat di bulan tiket selesai.</p>
            </div>
        @endif
    </div>

    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-100">
            <h2 class="text-sm font-bold text-slate-900">Daftar pekerjaan</h2>
        </div>
        <div class="divide-y divide-slate-100">
            @foreach($tickets as $ticket)
                <a href="{{ route('tickets.show', $ticket) }}" class="block px-5 py-3 hover:bg-slate-50">
                    <div class="flex items-center justify-between gap-3">
                        <span class="font-mono text-[11px] font-semibold text-indigo-600">{{ $ticket->ticket_number }}</span>
                        <span class="text-[11px] font-semibold {{ $ticket->report_status === 'blocked' ? 'text-rose-700' : 'text-slate-700' }}">
                            {{ match ($ticket->report_status) {
                                'delivered' => 'Delivered',
                                'in_progress' => 'In progress',
                                'blocked' => 'Blocked',
                                'finished' => 'Finished',
                                default => 'Belum diisi',
                            } }}
                            · {{ $ticket->progressPercent() === null ? '—' : $ticket->progressPercent().'%' }}
                        </span>
                    </div>
                    <p class="mt-1 text-xs font-semibold text-slate-900">{{ $ticket->title }}</p>
                    <p class="mt-0.5 text-[11px] text-slate-500">
                        {{ $ticket->assignedUser?->name ?? 'Belum dibagikan' }}
                        · Status bulan ini: {{ match ($ticket->report_status) {
                            'delivered' => 'Delivered',
                            'in_progress' => 'In progress',
                            'blocked' => 'Blocked',
                            'finished' => 'Finished',
                            default => $ticket->statusLabel(),
                        } }}
                        · {{ $ticket->category?->name }}
                        · Request date {{ $ticket->created_at->format('d M Y') }}
                        · Delivered date {{ $ticket->delivered_at ? $ticket->delivered_at->format('d M Y') : 'Belum diarahkan' }}
                    </p>
                    @if($ticket->progress_note)
                        <p class="mt-1 text-[11px] text-slate-600">{{ $ticket->progress_note }}</p>
                    @endif
                </a>
            @endforeach
        </div>
    </div>
@endif
@endsection
