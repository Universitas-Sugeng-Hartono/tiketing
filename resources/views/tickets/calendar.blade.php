@extends('layouts.app')

@section('content')
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
        <h1 class="text-lg font-bold text-slate-900">Kalender Tiket</h1>
        <p class="text-xs text-slate-500">
            Due date &amp; pekerjaan staff per hari.
            {{ $ticketCount }} due date · {{ $activityCount }} aktivitas staff.
        </p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <form method="GET" action="{{ route('tickets.calendar') }}" class="flex items-center gap-2">
            <input type="hidden" name="month" value="{{ $month }}">
            @if($staffUsers->count() > 1)
                <select name="staff_id" onchange="this.form.submit()"
                        class="px-3 py-2 rounded-lg bg-white border border-slate-200 text-xs font-semibold text-slate-700 focus:outline-none focus:border-slate-900">
                    <option value="">Semua staff</option>
                    @foreach($staffUsers as $staff)
                        <option value="{{ $staff->id }}" @selected((int) $selectedStaffId === (int) $staff->id)>
                            {{ $staff->name }}
                        </option>
                    @endforeach
                </select>
            @endif
        </form>
        <a href="{{ route('tickets.calendar', array_filter(['month' => $prevMonth, 'staff_id' => $selectedStaffId ?: null])) }}"
           class="px-3 py-2 rounded-lg bg-white border border-slate-200 text-xs font-semibold text-slate-700 hover:bg-slate-50">←</a>
        <span class="px-3 py-2 rounded-lg bg-slate-900 text-white text-xs font-semibold min-w-[140px] text-center">{{ $monthLabel }}</span>
        <a href="{{ route('tickets.calendar', array_filter(['month' => $nextMonth, 'staff_id' => $selectedStaffId ?: null])) }}"
           class="px-3 py-2 rounded-lg bg-white border border-slate-200 text-xs font-semibold text-slate-700 hover:bg-slate-50">→</a>
        <a href="{{ route('tickets.calendar', array_filter(['month' => now()->format('Y-m'), 'staff_id' => $selectedStaffId ?: null])) }}"
           class="px-3 py-2 rounded-lg bg-white border border-slate-200 text-xs font-semibold text-slate-600 hover:bg-slate-50">Hari ini</a>
    </div>
</div>

<div class="bg-white rounded-2xl border border-slate-200 shadow-2xs overflow-hidden">
    <div class="grid grid-cols-7 border-b border-slate-100 bg-slate-50/80">
        @foreach(['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $label)
            <div class="px-2 py-2.5 text-center text-[10px] font-bold uppercase tracking-wider text-slate-500">{{ $label }}</div>
        @endforeach
    </div>

    <div class="grid grid-cols-7 auto-rows-fr">
        @foreach($days as $day)
            @php
                $itemCount = $day['tickets']->count() + $day['activities']->count();
                $dueShown = $day['tickets']->take(2);
                $activitySlots = max(0, 4 - $dueShown->count());
                $activityShown = $day['activities']->take($activitySlots);
                $hiddenCount = $itemCount - $dueShown->count() - $activityShown->count();
            @endphp
            <div class="min-h-[120px] border-b border-r border-slate-100 p-2 {{ $day['in_month'] ? 'bg-white' : 'bg-slate-50/60' }} {{ $day['is_today'] ? 'ring-2 ring-inset ring-indigo-400' : '' }}">
                <div class="flex items-center justify-between mb-1.5">
                    <span class="text-[11px] font-bold {{ $day['in_month'] ? ($day['is_today'] ? 'text-indigo-700' : 'text-slate-700') : 'text-slate-300' }}">
                        {{ \Carbon\Carbon::parse($day['date'])->format('j') }}
                    </span>
                    @if($itemCount > 0)
                        <span class="text-[10px] font-semibold text-slate-400">{{ $itemCount }}</span>
                    @endif
                </div>
                <div class="space-y-1">
                    @foreach($dueShown as $ticket)
                        @php
                            $isOverdue = $ticket->due_date?->isPast()
                                && ! in_array($ticket->status, ['resolved', 'rejected', 'closed'], true);
                        @endphp
                        <a href="{{ route('tickets.show', $ticket) }}"
                           title="Due: {{ $ticket->ticket_number }} · {{ $ticket->assignedUser?->name ?? '—' }}"
                           class="block rounded-md px-1.5 py-1 text-[10px] leading-tight border transition
                                  {{ $isOverdue
                                      ? 'bg-rose-50 border-rose-100 text-rose-800 hover:bg-rose-100'
                                      : 'bg-amber-50 border-amber-200 text-amber-900 hover:bg-amber-100' }}">
                            <span class="font-bold block truncate">{{ $ticket->ticket_number }}</span>
                            <span class="truncate block opacity-80">Due · {{ $ticket->assignedUser?->name ?? '—' }}</span>
                        </a>
                    @endforeach

                    @foreach($activityShown as $log)
                        @php
                            $badge = $log->actionBadge();
                            $worker = $log->user?->name ?? 'Sistem';
                            $ticketNo = $log->ticket?->ticket_number ?? '—';
                        @endphp
                        <a href="{{ $log->ticket ? route('tickets.show', $log->ticket) : '#' }}"
                           title="{{ $worker }} · {{ $ticketNo }} · {{ $badge['label'] }}"
                           class="block rounded-md px-1.5 py-1 text-[10px] leading-tight border bg-sky-50 border-sky-100 text-sky-900 hover:bg-sky-100 transition">
                            <span class="font-bold block truncate">{{ $worker }}</span>
                            <span class="truncate block opacity-80">{{ $ticketNo }} · {{ $badge['label'] }}</span>
                        </a>
                    @endforeach

                    @if($hiddenCount > 0)
                        <p class="text-[10px] text-slate-400 px-1">+{{ $hiddenCount }} lagi</p>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>

<p class="mt-3 text-[11px] text-slate-400 flex flex-wrap gap-x-4 gap-y-1">
    <span class="inline-flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm bg-amber-300 border border-amber-400"></span> Due date staff</span>
    <span class="inline-flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm bg-rose-300 border border-rose-400"></span> Due lewat &amp; belum ditutup</span>
    <span class="inline-flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm bg-sky-300 border border-sky-400"></span> Aktivitas staff (progres, diskusi, selesai, dll.)</span>
</p>
@endsection
