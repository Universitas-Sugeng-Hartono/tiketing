@php
    $percent = $ticket->progressPercent() ?? 0;
@endphp
<div class="flex items-center gap-2">
    <div class="w-16 h-1.5 rounded-full bg-slate-100 overflow-hidden">
        <div class="h-full bg-emerald-500" style="width: {{ $percent }}%"></div>
    </div>
    <span class="text-xs font-bold text-emerald-700">{{ $percent }}%</span>
</div>
@if($ticket->work_status === 'blocked')
    <span class="text-[10px] font-bold text-rose-700 block mt-0.5">Terkendala</span>
@endif
