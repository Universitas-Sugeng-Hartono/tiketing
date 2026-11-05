@php
    $activeStaff = $staffProgress->filter(fn ($p) => $p['tickets'] > 0)->sortByDesc('tickets')->values();
    $staffIds = $staffProgress->map(fn ($p) => (int) $p['user']->id);
    $waitingTickets = $tickets->filter(fn ($ticket) => ! $staffIds->contains((int) $ticket->assigned_user_id))->values();
@endphp

<!-- Stat Summary Cards Row (Helpy Style) -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-2xs hover:shadow-xs transition flex flex-col justify-between">
        <div class="flex items-center justify-between">
            <span class="text-xs font-medium text-slate-500">Open Tickets</span>
            <span class="w-8 h-8 rounded-xl bg-slate-50 border border-slate-100 flex items-center justify-center text-slate-600">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
                </svg>
            </span>
        </div>
        <div class="mt-3">
            <div class="text-2xl font-bold text-slate-900 tracking-tight">{{ $openTicketsCount }}</div>
            <div class="flex items-center gap-1.5 mt-1 text-[11px] text-emerald-700 font-medium">
                <span class="px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700 border border-emerald-100 font-semibold">↑ Aktif</span>
                <span class="text-slate-400">tiket dalam antrean</span>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-2xs hover:shadow-xs transition flex flex-col justify-between">
        <div class="flex items-center justify-between">
            <span class="text-xs font-medium text-slate-500">Pending Response</span>
            <span class="w-8 h-8 rounded-xl bg-amber-50/60 border border-amber-100 flex items-center justify-center text-amber-700">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </span>
        </div>
        <div class="mt-3">
            <div class="text-2xl font-bold text-slate-900 tracking-tight">{{ $pendingCount }}</div>
            <div class="flex items-center gap-1.5 mt-1 text-[11px] text-amber-700 font-medium">
                <span class="px-1.5 py-0.5 rounded bg-amber-50 text-amber-700 border border-amber-200 font-semibold">Needs attention</span>
                <span class="text-slate-400">belum ditugaskan</span>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-2xs hover:shadow-xs transition flex flex-col justify-between">
        <div class="flex items-center justify-between">
            <span class="text-xs font-medium text-slate-500">In Progress</span>
            <span class="w-8 h-8 rounded-xl bg-sky-50 border border-sky-100 flex items-center justify-center text-sky-600">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
            </span>
        </div>
        <div class="mt-3">
            <div class="text-2xl font-bold text-slate-900 tracking-tight">{{ $inProgressCount }}</div>
            <div class="flex items-center gap-1.5 mt-1 text-[11px] text-slate-500">
                <span class="px-1.5 py-0.5 rounded bg-sky-50 text-sky-700 border border-sky-100 font-semibold">Teknisi</span>
                <span class="text-slate-400">sedang dikerjakan</span>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-2xs hover:shadow-xs transition flex flex-col justify-between">
        <div class="flex items-center justify-between">
            <span class="text-xs font-medium text-slate-500">Total Tim Staff</span>
            <span class="w-8 h-8 rounded-xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-600">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
            </span>
        </div>
        <div class="mt-3">
            <div class="text-2xl font-bold text-slate-900 tracking-tight">{{ $inboxStaff->count() }} <span class="text-xs font-normal text-slate-400">staff</span></div>
            <div class="flex items-center gap-1.5 mt-1 text-[11px] text-slate-500">
                <span class="px-1.5 py-0.5 rounded bg-slate-100 text-slate-700 font-semibold">{{ $activeStaff->count() }} aktif</span>
                <span class="text-slate-400">memegang tiket</span>
            </div>
        </div>
    </div>
</div>

{{-- Section: Pemantauan Staff Progres --}}
<div class="bg-white rounded-2xl border border-slate-200/90 shadow-2xs mb-6 overflow-hidden">
    <div class="px-5 py-3.5 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between gap-3">
        <div>
            <h2 class="text-xs font-bold text-slate-900 uppercase tracking-wider">Staff Progres</h2>
            <p class="text-[11px] text-slate-500 mt-0.5">Pantau nama staff, progres pengerjaan, dan jumlah tiket yang sedang dikerjakan.</p>
        </div>
        <span class="px-2.5 py-1 rounded-lg bg-indigo-50 text-indigo-700 text-xs font-semibold border border-indigo-100">
            {{ $activeStaff->count() }} Staff Aktif
        </span>
    </div>

    <div class="divide-y divide-slate-100">
        @forelse($staffProgress as $progress)
            <div class="px-5 py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4 hover:bg-slate-50/50 transition">
                <div class="flex items-center gap-3 min-w-0">
                    @if($progress['user']->avatar_url)
                        <img src="{{ $progress['user']->avatar_url }}" alt="{{ $progress['user']->name }}"
                             class="w-9 h-9 rounded-xl object-cover ring-1 ring-slate-200 shrink-0">
                    @else
                        <div class="w-9 h-9 rounded-xl bg-slate-900 text-white flex items-center justify-center text-xs font-bold shrink-0">
                            {{ strtoupper(substr($progress['user']->name, 0, 1)) }}
                        </div>
                    @endif
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-sm font-bold text-slate-900">{{ $progress['user']->name }}</span>
                            @if($progress['user']->keahlian)
                                <span class="text-[10px] px-2 py-0.5 rounded-md bg-slate-100 text-slate-600 font-medium border border-slate-200">
                                    {{ $progress['user']->keahlian }}
                                </span>
                            @endif
                        </div>
                        <p class="text-[11px] text-slate-500 mt-0.5">
                            <span class="font-semibold text-slate-700">{{ $progress['tickets'] }} tiket sedang dikerjakan</span>
                            · <span class="font-semibold text-amber-700">{{ $progress['achievement'] ?? 0 }} poin</span>
                            @if(($progress['blocked'] ?? 0) > 0)
                                · <span class="font-bold text-rose-700">{{ $progress['blocked'] }} blocked</span>
                            @endif
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-4 self-end sm:self-auto shrink-0">
                    <div class="flex items-center gap-2">
                        <div class="w-28 h-2 rounded-full bg-slate-100 overflow-hidden">
                            <div class="h-full bg-emerald-500" style="width: {{ $progress['percent'] ?? 0 }}%"></div>
                        </div>
                        <span class="text-xs font-bold text-emerald-700 w-9 text-right">{{ $progress['percent'] === null ? '—' : $progress['percent'] . '%' }}</span>
                    </div>

                    <!-- Tombol DETAIL untuk melihat tiket-tiket milik staff ini -->
                    <button type="button"
                            @click="staffDetail = {{ (int) $progress['user']->id }}"
                            class="px-3 py-1.5 rounded-xl border border-slate-200 hover:bg-slate-100 text-xs font-semibold text-slate-700 transition flex items-center gap-1.5 shadow-2xs">
                        <span>Detail</span>
                        <span class="text-slate-400">&rarr;</span>
                    </button>
                </div>
            </div>
        @empty
            <p class="px-5 py-8 text-center text-xs text-slate-400">Belum ada staff yang terdaftar dalam unit ini.</p>
        @endforelse
    </div>
</div>

<!-- Section: Antrean Tiket Masuk (Clean Data Table ala Helpy) -->
<div class="bg-white rounded-2xl border border-slate-200/90 shadow-2xs overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-100 bg-white flex flex-col md:flex-row md:items-center justify-between gap-3">
        <div>
            <h2 class="text-sm font-bold text-slate-900">Antrean Tiket Masuk</h2>
            <p class="text-xs text-slate-500 mt-0.5">Tetapkan prioritas, tetapkan poin bobot pekerjaan (%), dan deliver ke staff teknisi.</p>
        </div>

        <form action="{{ route('tickets.inbox') }}" method="GET" class="flex items-center gap-2 flex-wrap">
            <input type="hidden" name="scope" value="{{ $scope }}">
            <input type="text" name="search" value="{{ $search }}"
                   placeholder="Cari No. Tiket, Judul, atau Nama..."
                   class="px-3 py-1.5 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:border-slate-900">
            <select name="category_id" onchange="this.form.submit()"
                    class="px-3 py-1.5 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-700 font-medium focus:outline-none">
                <option value="">Semua Kategori</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" {{ (string) $categoryId === (string) $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="px-3 py-1.5 rounded-xl bg-slate-900 text-white text-xs font-medium hover:bg-slate-800 transition">Filter</button>
            @if($search || $categoryId || $statusFilter || !empty($priorityFilter))
                <a href="{{ route('tickets.inbox', ['scope' => $scope]) }}" class="px-2.5 py-1.5 rounded-xl bg-rose-50 text-rose-700 text-xs font-semibold hover:bg-rose-100 transition">Reset</a>
            @endif
        </form>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="border-b border-slate-200/80 bg-slate-50/60 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">
                    <th class="py-3.5 px-4 min-w-[150px]">Ticket ID</th>
                    <th class="py-3.5 px-4 min-w-[240px]">Subject</th>
                    <th class="py-3.5 px-4 min-w-[150px]">Customer</th>
                    <th class="py-3.5 px-4 min-w-[150px]">Channel</th>
                    <th class="py-3.5 px-4 min-w-[140px]">Priority</th>
                    <th class="py-3.5 px-4 min-w-[140px]">Progres</th>
                    <th class="py-3.5 px-4 min-w-[100px]">Status</th>
                    <th class="py-3.5 px-4 min-w-[110px] text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-xs">
                @forelse($waitingTickets as $ticket)
                    @php
                        $initial = strtoupper(substr($ticket->creator?->name ?? 'U', 0, 1));
                    @endphp
                    <tr class="hover:bg-slate-50/70 transition">
                        <td class="py-4 px-4 align-top whitespace-nowrap">
                            <a href="{{ route('tickets.show', $ticket) }}" class="font-mono font-bold text-indigo-600 hover:text-indigo-800 transition">
                                {{ $ticket->ticket_number }}
                            </a>
                            <div class="mt-1">
                                @include('tickets.partials.dates', ['ticket' => $ticket, 'format' => 'd M Y'])
                            </div>
                        </td>

                        <td class="py-4 px-4 align-top">
                            <a href="{{ route('tickets.show', $ticket) }}" class="font-semibold text-slate-900 hover:text-indigo-600 block leading-snug">
                                {{ $ticket->title }}
                            </a>
                            <div class="flex items-center gap-2 mt-1.5 flex-wrap">
                                @if($ticket->attachment_path)
                                    <button type="button"
                                            @click="openDocPreview('{{ route('tickets.preview', $ticket) }}', '{{ route('tickets.attachment', $ticket) }}', '{{ addslashes($ticket->attachment_name ?? 'Dokumen Lampiran') }}')"
                                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-indigo-50/70 hover:bg-indigo-100 text-indigo-700 text-[10px] font-medium border border-indigo-100 transition">
                                        {{ $ticket->isImageAttachment() ? '🖼️ Gambar Bukti' : '📎 Preview Dokumen' }}
                                    </button>
                                @endif
                                @if(($ticket->problems_count ?? 0) > 0)
                                    <span class="inline-flex items-center gap-1 text-[10px] font-semibold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-100">
                                        ✓ {{ $ticket->done_problems_count ?? 0 }}/{{ $ticket->problems_count }} poin
                                    </span>
                                @endif
                            </div>
                        </td>

                        <td class="py-4 px-4 align-top whitespace-nowrap">
                            <div class="flex items-center gap-2.5">
                                <div class="w-7 h-7 rounded-full bg-slate-100 border border-slate-200 text-slate-700 flex items-center justify-center font-bold text-xs shrink-0">
                                    {{ $initial }}
                                </div>
                                <div class="min-w-0">
                                    <span class="font-semibold text-slate-900 block truncate">{{ $ticket->creator?->name ?? 'Pemohon' }}</span>
                                    <span class="text-[11px] text-slate-400 block truncate">{{ $ticket->creatorRole?->name }}</span>
                                </div>
                            </div>
                        </td>

                        <td class="py-4 px-4 align-top">
                            <span class="inline-block text-xs text-slate-700 font-medium">
                                {{ $ticket->category?->name ?? 'Umum' }}
                            </span>
                        </td>

                        <!-- Quick Inline Priority Changer -->
                        <td class="py-4 px-4 align-top whitespace-nowrap">
                            <div x-data="{
                                open: false,
                                priority: '{{ $ticket->priority ?? 'medium' }}',
                                label: '{{ $ticket->priorityLabel() }}',
                                loading: false,
                                async setPriority(newVal, labelText) {
                                    this.loading = true;
                                    this.open = false;
                                    try {
                                        const formData = new FormData();
                                        formData.append('_token', '{{ csrf_token() }}');
                                        formData.append('priority', newVal);
                                        const res = await fetch('{{ route('tickets.priority', $ticket) }}', {
                                            method: 'POST',
                                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                                            body: formData
                                        });
                                        const data = await res.json();
                                        if (data.success) {
                                            this.priority = newVal;
                                            this.label = labelText;
                                            window.dispatchEvent(new CustomEvent('toast', { detail: { message: data.message } }));
                                        }
                                    } catch (err) {
                                        console.error(err);
                                    } finally {
                                        this.loading = false;
                                    }
                                }
                            }" class="relative inline-block text-left">
                                <button @click="open = !open" type="button"
                                        class="inline-flex items-center gap-2 px-2.5 py-1 rounded-lg border border-slate-200/80 bg-white hover:bg-slate-50 text-xs font-medium text-slate-700 transition shadow-2xs">
                                    <span class="w-2 h-2 rounded-full shrink-0"
                                          :class="{
                                              'bg-emerald-500': priority === 'low',
                                              'bg-amber-400': priority === 'medium',
                                              'bg-orange-500': priority === 'high',
                                              'bg-rose-600': priority === 'urgent'
                                          }"></span>
                                    <span x-text="label" class="truncate max-w-[90px]"></span>
                                    <svg class="w-3 h-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>

                                <div x-show="open" @click.outside="open = false" x-cloak
                                     class="absolute left-0 mt-1 w-44 rounded-xl bg-white border border-slate-200 shadow-xl p-1.5 z-30">
                                    <div class="px-2 py-1 text-[10px] font-bold text-slate-400 uppercase tracking-wider">Set Prioritas</div>
                                    <button type="button" @click="setPriority('low', 'Rendah')" class="w-full flex items-center justify-between px-2.5 py-1.5 rounded-lg text-xs hover:bg-slate-50 text-slate-600">
                                        <span class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-emerald-500"></span> Low / Rendah</span>
                                        <span x-show="priority === 'low'" class="text-indigo-600 font-bold">✓</span>
                                    </button>
                                    <button type="button" @click="setPriority('medium', 'Normal')" class="w-full flex items-center justify-between px-2.5 py-1.5 rounded-lg text-xs hover:bg-slate-50 text-slate-600">
                                        <span class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-amber-400"></span> Medium / Normal</span>
                                        <span x-show="priority === 'medium'" class="text-indigo-600 font-bold">✓</span>
                                    </button>
                                    <button type="button" @click="setPriority('high', 'Tinggi')" class="w-full flex items-center justify-between px-2.5 py-1.5 rounded-lg text-xs hover:bg-slate-50 text-slate-600">
                                        <span class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-orange-500"></span> High / Tinggi</span>
                                        <span x-show="priority === 'high'" class="text-indigo-600 font-bold">✓</span>
                                    </button>
                                    <button type="button" @click="setPriority('urgent', 'Mendesak')" class="w-full flex items-center justify-between px-2.5 py-1.5 rounded-lg text-xs hover:bg-slate-50 text-slate-600">
                                        <span class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-rose-600"></span> Urgent / Mendesak</span>
                                        <span x-show="priority === 'urgent'" class="text-indigo-600 font-bold">✓</span>
                                    </button>
                                </div>
                            </div>
                        </td>

                        <td class="py-4 px-4 align-top whitespace-nowrap">
                            @include('tickets.partials.progress-meter', ['ticket' => $ticket])
                        </td>

                        <td class="py-4 px-4 align-top whitespace-nowrap">
                            <span class="inline-block text-[11px] font-medium px-2.5 py-1 rounded-md border {{ $statusColors[$ticket->status] ?? 'bg-slate-100 text-slate-700' }}">
                                {{ $ticket->statusLabel() }}
                            </span>
                        </td>

                        <td class="py-4 px-4 align-top text-right whitespace-nowrap">
                            <a href="{{ route('tickets.show', $ticket) }}"
                               class="inline-flex items-center gap-1 px-3.5 py-1.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-medium text-xs transition shadow-2xs">
                                <span>Proses</span>
                                <span>&rarr;</span>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="py-12 px-4 text-center">
                            <div class="max-w-xs mx-auto text-center">
                                <span class="w-10 h-10 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center mx-auto mb-2">✓</span>
                                <p class="text-xs font-semibold text-slate-700">Semua tiket telah dibagikan ke staf</p>
                                <p class="text-[11px] text-slate-400 mt-0.5">Tidak ada antrean baru yang menunggu pendelegasian.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
