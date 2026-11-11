<p class="text-[11px] text-slate-500">Request date {{ $ticket->created_at->format($format ?? 'd M Y, H:i') }}</p>
<p class="text-[11px] text-slate-500">Delivered date {{ $ticket->delivered_at ? $ticket->delivered_at->format($format ?? 'd M Y, H:i') : 'Belum diarahkan' }}</p>
@if($ticket->due_date)
    <p class="text-[11px] {{ $ticket->due_date->isPast() && ! in_array($ticket->status, ['resolved', 'rejected', 'closed'], true) ? 'text-rose-600 font-semibold' : 'text-slate-500' }}">
        Due date {{ $ticket->due_date->format($format ?? 'd M Y') }}
    </p>
@endif
