<!DOCTYPE html>
<html lang="id" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lacak Tiket — SuperTicket USH</title>
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
    <div class="fixed inset-0 pointer-events-none -z-10 overflow-hidden">
        <div class="absolute -top-40 -left-40 w-[500px] h-[500px] bg-indigo-200/30 rounded-full blur-3xl"></div>
        <div class="absolute top-1/2 -right-40 w-[450px] h-[450px] bg-sky-200/25 rounded-full blur-3xl"></div>
    </div>

    <div class="max-w-lg mx-auto px-4 py-10 sm:py-16">
        <div class="mb-6 text-center">
            <p class="text-[11px] font-bold uppercase tracking-wider text-indigo-600">SuperTicket USH</p>
            <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight mt-1">Lacak Tiket</h1>
            <p class="text-xs text-slate-500 mt-1.5">
                Cari dari judul, nomor tiket, nama pemohon, atau kata di isi tiket. Tidak perlu login.
            </p>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 sm:p-6">
            <form action="{{ route('tickets.track.search') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Kata kunci</label>
                    <input type="text" name="q" value="{{ $q }}" required autofocus
                           placeholder="Contoh: SIAKAD, Ian, wifi, TKT-202611"
                           class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:border-slate-900">
                    @error('q')
                        <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
                <button type="submit"
                        class="w-full px-4 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-sm font-semibold transition">
                    Cari Tiket
                </button>
            </form>
        </div>

        @if(($results ?? collect())->isNotEmpty())
            <div class="mt-5 bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="px-4 py-3 border-b border-slate-100">
                    <p class="text-xs font-bold text-slate-900">{{ $results->count() }} tiket ditemukan</p>
                    <p class="text-[11px] text-slate-500">Pilih tiket untuk melihat progres.</p>
                </div>
                <div class="divide-y divide-slate-100">
                    @foreach($results as $ticket)
                        <a href="{{ route('tickets.track.show', ['ticketNumber' => $ticket->ticket_number]) }}"
                           class="block px-4 py-3 hover:bg-slate-50 transition">
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-mono text-[11px] font-bold text-indigo-700">{{ $ticket->ticket_number }}</span>
                                <span class="text-[10px] font-semibold text-slate-500">{{ $ticket->statusLabel() }}</span>
                            </div>
                            <p class="text-xs font-semibold text-slate-900 mt-0.5 leading-snug">{{ $ticket->title }}</p>
                            <p class="text-[11px] text-slate-500 mt-0.5">
                                {{ $ticket->creator?->name ?? 'Pemohon' }}
                                · {{ $ticket->categoryDisplayName() }}
                                · Progres {{ $ticket->progressPercent() === null ? '—' : $ticket->progressPercent().'%' }}
                            </p>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="mt-5 text-center text-xs">
            <a href="{{ route('login') }}" class="font-semibold text-indigo-600 hover:text-indigo-700">Masuk akun</a>
        </div>
    </div>
</body>
</html>
