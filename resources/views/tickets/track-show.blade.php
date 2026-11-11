<!DOCTYPE html>
<html lang="id" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $ticket->ticket_number }} — Lacak Tiket</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                    }
                }
            }
        }
    </script>
</head>
<body class="min-h-full font-sans text-slate-800 bg-slate-50">
    <div class="max-w-2xl mx-auto px-4 py-8 sm:py-12">
        <div class="flex items-center justify-between gap-3 mb-5">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-wider text-indigo-600">Status Publik</p>
                <h1 class="text-xl font-extrabold text-slate-900 tracking-tight">{{ $ticket->ticket_number }}</h1>
            </div>
            <a href="{{ route('tickets.track') }}" class="text-xs font-semibold text-slate-600 hover:text-slate-900">Cari lagi</a>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="p-5 border-b border-slate-100">
                <h2 class="text-sm font-bold text-slate-900 leading-snug">{{ $ticket->title }}</h2>
                <p class="text-[11px] text-slate-500 mt-1">{{ $ticket->categoryDisplayName() }}</p>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 p-5 border-b border-slate-100">
                <div>
                    <p class="text-[10px] uppercase font-bold tracking-wider text-slate-400">Status</p>
                    <p class="text-xs font-bold text-slate-900 mt-1">{{ $ticket->statusLabel() }}</p>
                </div>
                <div>
                    <p class="text-[10px] uppercase font-bold tracking-wider text-slate-400">Progres</p>
                    <p class="text-xs font-bold text-emerald-700 mt-1">{{ $ticket->progressPercent() === null ? '—' : $ticket->progressPercent().'%' }}</p>
                </div>
                <div>
                    <p class="text-[10px] uppercase font-bold tracking-wider text-slate-400">Tahap</p>
                    <p class="text-xs font-bold text-slate-900 mt-1">{{ $ticket->currentRole?->name ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-[10px] uppercase font-bold tracking-wider text-slate-400">Estimasi</p>
                    <p class="text-xs font-bold text-slate-900 mt-1">{{ $ticket->due_date ? $ticket->due_date->format('d M Y') : '—' }}</p>
                </div>
            </div>

            @if($ticket->progress_percent !== null || $ticket->work_status)
                <div class="px-5 py-4 border-b border-slate-100">
                    <div class="flex items-center justify-between gap-3 mb-2">
                        <span class="text-xs font-semibold text-slate-700">Progres pengerjaan</span>
                        <span class="text-xs font-bold text-emerald-700">{{ $ticket->progressPercent() ?? 0 }}%</span>
                    </div>
                    <div class="h-2 rounded-full bg-slate-100 overflow-hidden">
                        <div class="h-full bg-emerald-500" style="width: {{ $ticket->progressPercent() ?? 0 }}%"></div>
                    </div>
                    @if($ticket->workStatusLabel())
                        <p class="text-[11px] text-slate-500 mt-2">Status kerja: <strong class="text-slate-800">{{ $ticket->workStatusLabel() }}</strong></p>
                    @endif
                    @if($ticket->assignedUser)
                        <p class="text-[11px] text-slate-500 mt-0.5">Dikerjakan: <strong class="text-slate-800">{{ $ticket->assignedUser->name }}</strong></p>
                    @endif
                </div>
            @endif

            <div class="p-5">
                <h3 class="text-xs font-bold text-slate-900 mb-3">Percakapan &amp; update terakhir</h3>
                <div class="space-y-3">
                    @forelse($updates as $log)
                        @php $badge = $log->actionBadge(); @endphp
                        <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-3">
                            <div class="flex items-center justify-between gap-2 mb-1">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-{{ $badge['color'] }}-700">{{ $badge['label'] }}</span>
                                <span class="text-[10px] text-slate-400">{{ $log->created_at->format('d M Y, H:i') }}</span>
                            </div>
                            <p class="text-xs text-slate-700 leading-relaxed">{{ $log->note }}</p>
                            <p class="text-[11px] text-slate-400 mt-1">
                                {{ $log->user?->name ?? 'Sistem' }}
                                @if($log->fromRole)
                                    · {{ $log->fromRole->name }}
                                @endif
                            </p>
                        </div>
                    @empty
                        <p class="text-xs text-slate-400">Belum ada update publik pada tiket ini.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <p class="text-center text-[11px] text-slate-400 mt-5">
            Butuh aksi lebih lanjut?
            <a href="{{ route('login') }}" class="font-semibold text-indigo-600 hover:text-indigo-700">Masuk ke SuperTicket</a>
        </p>
    </div>
</body>
</html>
