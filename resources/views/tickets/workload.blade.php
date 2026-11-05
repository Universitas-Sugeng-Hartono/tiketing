@extends('layouts.app')

@section('content')
@php
    $statusColor = [
        'blocked' => 'text-rose-700',
        'in_progress' => 'text-sky-700',
        'finished' => 'text-emerald-700',
        'delivered' => 'text-indigo-700',
    ];
@endphp

<div class="mb-5 flex flex-col sm:flex-row sm:items-end justify-between gap-3">
    <div>
        <h1 class="text-lg font-bold text-slate-900">Pekerjaan Divisi</h1>
        <p class="text-xs text-slate-500">Pekerjaan yang sedang dikerjakan IT dan setiap divisi.</p>
    </div>
    <form method="GET" action="{{ route('tickets.workload') }}" class="flex items-center gap-2 w-full sm:w-auto">
        <input type="text" name="search" value="{{ $search ?? '' }}"
               placeholder="Cari no tiket, judul, staff, kategori..."
               class="flex-1 sm:w-72 px-3 py-2 rounded-lg border border-slate-200 bg-white text-xs focus:outline-none focus:border-slate-900">
        <button type="submit" class="px-3.5 py-2 rounded-lg bg-slate-900 hover:bg-slate-800 text-white text-xs font-semibold">
            Cari
        </button>
        @if(!empty($search))
            <a href="{{ route('tickets.workload') }}" class="px-3 py-2 rounded-lg border border-slate-200 text-xs text-slate-600 hover:bg-slate-50">Reset</a>
        @endif
    </form>
</div>

<style>
    @keyframes rank-rise {
        from { transform: scaleY(0); }
        to { transform: scaleY(1); }
    }
    .rank-bar {
        transform-origin: bottom;
        animation: rank-rise .6s cubic-bezier(.22,1,.36,1) both;
    }
    .rank-bar-1 { height: 96px; background: linear-gradient(180deg,#fbbf24,#f59e0b); }
    .rank-bar-2 { height: 72px; background: linear-gradient(180deg,#cbd5e1,#94a3b8); }
    .rank-bar-3 { height: 56px; background: linear-gradient(180deg,#d8a878,#b45309); }
    .contrib-wrap { display: inline-block; min-width: max-content; }
    .contrib-months {
        display: flex;
        gap: 3px;
        margin-bottom: 4px;
        height: 14px;
        padding-left: 28px;
    }
    .contrib-month {
        flex: 0 0 auto;
        font-size: 10px;
        line-height: 14px;
        color: #94a3b8;
        white-space: nowrap;
        overflow: hidden;
    }
    .contrib-body { display: flex; align-items: flex-start; gap: 6px; }
    .contrib-weekdays {
        display: grid;
        grid-template-rows: repeat(7, 10px);
        gap: 3px;
        font-size: 9px;
        line-height: 10px;
        color: #94a3b8;
        width: 22px;
        flex-shrink: 0;
    }
    .contrib-grid {
        display: grid;
        grid-auto-flow: column;
        grid-template-rows: repeat(7, 10px);
        grid-auto-columns: 10px;
        gap: 3px;
    }
    .contrib-cell { width: 10px; height: 10px; border-radius: 2px; }
    .contrib-0 { background: #ebedf0; }
    .contrib-1 { background: #9be9a8; }
    .contrib-2 { background: #40c463; }
    .contrib-3 { background: #30a14e; }
    .contrib-4 { background: #216e39; }
</style>

@php $contributionRendered = false; @endphp
@forelse($groups as $division => $tickets)
    <section class="mb-6">
        <div class="mb-3 flex items-center justify-between gap-3">
            <h2 class="text-sm font-bold text-slate-900">{{ $division }}</h2>
            <span class="text-[11px] text-slate-500">{{ $tickets->count() }} pekerjaan</span>
        </div>
        @php
            $rows = $ranking[$division] ?? collect();
            $showFullRanking = $canSeeFullRanking ?? false;
            $mine = null;
            if (!$showFullRanking) {
                foreach ($rows as $index => $row) {
                    if ((int) ($row['id'] ?? 0) === (int) $user->id) {
                        $mine = $row;
                        $mine['place'] = $index + 1;
                        break;
                    }
                }
            }
        @endphp
        @if($showFullRanking && $rows->isNotEmpty())
            @php
                $podium = $rows->take(3)->values();
                $rest = $rows->slice(3);
                $medals = [1 => '🥇', 2 => '🥈', 3 => '🥉'];
                $visualOrder = [1 => 'order-2', 2 => 'order-1', 3 => 'order-3'];
            @endphp
            <section class="mb-3 overflow-hidden rounded-xl border border-slate-200 bg-white">
                <div class="border-b border-slate-100 px-4 py-2.5">
                    <h2 class="text-sm font-bold text-slate-900">Ranking achievement</h2>
                    <p class="text-[11px] text-slate-500">Staff IT, diurutkan dari poin tiket yang sudah selesai.</p>
                </div>
                <ol class="flex items-end justify-center gap-2 px-3 pt-5 sm:gap-3 sm:px-6">
                    @foreach($podium as $index => $row)
                        @php
                            $place = $index + 1;
                            $isMe = (int) ($row['id'] ?? 0) === (int) $user->id;
                        @endphp
                        <li class="flex min-w-0 flex-1 flex-col items-center {{ $visualOrder[$place] }}">
                            <span class="sr-only">Peringkat {{ $place }}</span>
                            <span class="relative mb-2 {{ $place === 1 ? 'h-14 w-14 text-lg' : 'h-12 w-12 text-base' }}">
                                @if($row['avatar_url'])
                                    <img src="{{ $row['avatar_url'] }}" alt="" class="h-full w-full rounded-full object-cover ring-2 ring-white shadow-md">
                                @else
                                    <span class="flex h-full w-full items-center justify-center rounded-full font-extrabold text-white ring-2 ring-white shadow-md" style="background: {{ $row['color'] }}">{{ $row['initials'] }}</span>
                                @endif
                                <span class="absolute -bottom-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full bg-white text-xs shadow">{{ $medals[$place] }}</span>
                            </span>
                            <span class="w-full truncate px-1 text-center text-xs font-bold text-slate-900">{{ $row['name'] }}</span>
                            @if($isMe)
                                <span class="mt-0.5 rounded-full bg-emerald-600 px-1.5 py-0.5 text-[10px] font-bold text-white">Kamu</span>
                            @endif
                            <span class="mb-2 text-[11px] font-extrabold text-indigo-500">{{ $row['points'] }} poin</span>
                            <div class="rank-bar rank-bar-{{ $place }} flex w-full items-start justify-center rounded-t-lg pt-2 text-lg font-black text-white/90">{{ $place }}</div>
                        </li>
                    @endforeach
                </ol>
                @if($rest->isNotEmpty())
                    <ol class="border-t border-slate-100">
                        @foreach($rest as $index => $row)
                            @php $isMe = (int) ($row['id'] ?? 0) === (int) $user->id; @endphp
                            <li class="flex items-center gap-3 border-b border-slate-100 px-4 py-2.5 last:border-b-0 {{ $isMe ? 'bg-emerald-50' : '' }}">
                                <span class="w-5 shrink-0 text-center text-[13px] font-extrabold text-slate-400">{{ $index + 1 }}</span>
                                @if($row['avatar_url'])
                                    <img src="{{ $row['avatar_url'] }}" alt="" class="h-7 w-7 shrink-0 rounded-full object-cover">
                                @else
                                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-[11px] font-extrabold text-white" style="background: {{ $row['color'] }}">{{ $row['initials'] }}</span>
                                @endif
                                <span class="min-w-0 flex-1 truncate text-sm font-semibold text-slate-700">
                                    {{ $row['name'] }}
                                    @if($isMe)
                                        <span class="ml-1 rounded-full bg-emerald-600 px-1.5 py-0.5 text-[10px] font-bold text-white">Kamu</span>
                                    @endif
                                </span>
                                <span class="shrink-0 text-[13px] font-extrabold tabular-nums text-slate-900">{{ $row['points'] }} poin</span>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>
        @elseif($mine)
            <section class="mb-3 rounded-xl border border-slate-200 bg-white px-4 py-3">
                <h2 class="text-sm font-bold text-slate-900">Ranking achievement</h2>
                <p class="text-[11px] text-slate-500">Peringkat kamu.</p>
                <div class="mt-3 flex items-center gap-3">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-amber-400 text-sm font-black text-amber-950">{{ $mine['place'] }}</span>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="truncate text-sm font-bold text-slate-900">{{ $mine['name'] }}</span>
                            <span class="rounded-full bg-emerald-600 px-1.5 py-0.5 text-[10px] font-bold text-white">Kamu</span>
                        </div>
                        <span class="text-[11px] text-slate-400">{{ $mine['tickets'] }} tiket</span>
                    </div>
                    <span class="ml-auto shrink-0 text-sm font-extrabold text-indigo-500">{{ $mine['points'] }} poin</span>
                </div>
            </section>
        @endif

        @if(! $contributionRendered && !empty($myContribution['cells']) && (($showFullRanking && $rows->isNotEmpty()) || $mine))
            @php $contributionRendered = true; @endphp
            <section class="mb-3 overflow-hidden rounded-xl border border-slate-200 bg-white">
                <div class="border-b border-slate-100 px-4 py-2.5 flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-bold text-slate-900">Kontribusi pengerjaan kamu</h2>
                        <p class="text-[11px] text-slate-500">12 bulan. Hijau muda ≈ 10% progres, makin gelap seiring tiket dikerjakan.</p>
                    </div>
                    <span class="shrink-0 text-[11px] font-semibold text-slate-500">{{ $myContribution['total'] ?? 0 }} hari dikerjakan</span>
                </div>
                <div class="px-4 py-3 overflow-x-auto">
                    <div class="contrib-wrap">
                        <div class="contrib-months">
                            @foreach(($myContribution['month_labels'] ?? []) as $month)
                                <span class="contrib-month" style="width: {{ max(1, (int) $month['span']) * 13 - 3 }}px">{{ $month['label'] }}</span>
                            @endforeach
                        </div>
                        <div class="contrib-body">
                            <div class="contrib-weekdays" aria-hidden="true">
                                <span></span>
                                <span>Sen</span>
                                <span></span>
                                <span>Rab</span>
                                <span></span>
                                <span>Jum</span>
                                <span></span>
                            </div>
                            <div class="contrib-grid">
                                @foreach(($myContribution['cells'] ?? []) as $cell)
                                    <span class="contrib-cell contrib-{{ $cell['level'] }}" title="{{ $cell['date'] }}: progres {{ $cell['percent'] }}%"></span>
                                @endforeach
                            </div>
                        </div>
                        <div class="mt-2 flex items-center justify-end gap-1.5 text-[10px] text-slate-400">
                            <span>≈10%</span>
                            <span class="contrib-cell contrib-0"></span>
                            <span class="contrib-cell contrib-1" title="1–24%"></span>
                            <span class="contrib-cell contrib-2" title="25–49%"></span>
                            <span class="contrib-cell contrib-3" title="50–74%"></span>
                            <span class="contrib-cell contrib-4" title="75–100%"></span>
                            <span>100%</span>
                        </div>
                    </div>
                </div>
            </section>
        @endif

        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
            @foreach($tickets as $ticket)
                @php
                    $percent = $ticket->progressPercent();
                    $finished = $ticket->work_status === 'finished';
                @endphp
                <article class="relative overflow-hidden rounded-xl border px-4 py-3 {{ $finished ? 'border-slate-200 bg-slate-100' : 'border-slate-200 bg-white' }}">
                    <div class="{{ $finished ? 'opacity-40' : '' }}">
                        <div class="flex items-start justify-between gap-3">
                            <h3 class="text-sm font-semibold leading-snug {{ $finished ? 'text-slate-500' : 'text-slate-900' }}">{{ $ticket->title }}</h3>
                            <span class="shrink-0 text-[11px] font-semibold {{ $finished ? 'text-slate-400' : ($statusColor[$ticket->work_status] ?? 'text-slate-600') }}">
                                {{ $ticket->workStatusLabel() ?? $ticket->statusLabel() }}
                                · {{ $percent === null ? '—' : $percent.'%' }}
                            </span>
                        </div>
                        <p class="mt-1.5 text-[11px] text-slate-500">
                            {{ $ticket->ticket_number }}
                            · {{ $ticket->assignedUser?->name ?? 'Belum ada petugas' }}
                            @if($ticket->achievement_points && (($canSeeFullRanking ?? false) || (int) $ticket->assigned_user_id === (int) $user->id))
                                · {{ $ticket->achievement_points }} poin
                            @endif
                            @if($ticket->category)
                                · {{ $ticket->category->name }}
                            @endif
                        </p>
                        <p class="mt-1 text-[11px] text-slate-400">
                            Request date {{ $ticket->created_at->format('d M Y') }}
                            · Delivered date {{ $ticket->delivered_at ? $ticket->delivered_at->format('d M Y') : 'Belum diarahkan' }}
                        </p>
                    </div>
                    @if($finished)
                        <div class="absolute inset-0 flex items-center justify-center">
                            <span class="rounded-md bg-slate-200 px-3 py-1 text-sm font-semibold tracking-wide text-slate-500">Selesai</span>
                        </div>
                    @endif
                </article>
            @endforeach
        </div>
    </section>
@empty
    <div class="rounded-xl border border-slate-200 bg-white px-5 py-8 text-center text-xs text-slate-400">
        @if(!empty($search))
            Tidak ada pekerjaan yang cocok dengan pencarian <strong>{{ $search }}</strong>.
        @else
            Belum ada pekerjaan yang sedang dikerjakan.
        @endif
    </div>
@endforelse
@endsection
