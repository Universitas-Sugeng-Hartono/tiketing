@extends('layouts.app')

@section('content')
<div class="max-w-xl mx-auto"
     x-data="{
        selectedCategory: '{{ old('category_id', $categories->first()?->id) }}',
        chains: {{ Js::from($categoryChains) }},
        fileName: '',
        submitting: false
     }">

    <div class="mb-4">
        <h1 class="text-base font-bold text-slate-900">Ajukan Tiket Baru</h1>
        <p class="text-xs text-slate-500">Prioritas tiket akan ditentukan oleh role penerima pada tahap selanjutnya.</p>
    </div>

    <div class="bg-white rounded-xl p-6 border border-slate-200/90">
        <form action="{{ route('tickets.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4"
              @submit="if (submitting) { $event.preventDefault(); return false; } submitting = true">
            @csrf

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Kategori Layanan</label>
                <select name="category_id" x-model="selectedCategory"
                        class="w-full px-3 py-2 rounded-lg border border-slate-200 text-xs bg-white focus:outline-none focus:border-slate-900">
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}">
                            {{ $cat->name }} ({{ $cat->workflow_type === 'tiered_approval' ? 'Approval Berjenjang' : 'Eskalasi/Disposisi' }})
                        </option>
                    @endforeach
                </select>

                <!-- Info Alur Hirarki Ringkas -->
                <div x-show="selectedCategory && chains[selectedCategory]" x-cloak
                     class="mt-2 px-3 py-2 rounded-lg bg-slate-50 border border-slate-200/70 text-[11px] text-slate-600 flex items-center justify-between">
                    <span>Alur Tujuan: <strong class="text-slate-900" x-text="(chains[selectedCategory]?.chain_names || []).join(' → ')"></strong></span>
                    <span class="text-slate-400" x-text="chains[selectedCategory]?.workflow_label"></span>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Judul Permohonan / Kendala</label>
                <input type="text" name="title" value="{{ old('title') }}" required
                       placeholder="Contoh: Permohonan Izin Dispensasi Kelas"
                       class="w-full px-3 py-2 rounded-lg border border-slate-200 text-xs focus:outline-none focus:border-slate-900">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Deskripsi Lengkap</label>
                <textarea name="description" rows="4" required
                          placeholder="Tuliskan rincian keperluan atau kendala Anda..."
                          class="w-full px-3 py-2 rounded-lg border border-slate-200 text-xs focus:outline-none focus:border-slate-900">{{ old('description') }}</textarea>
            </div>

            <!-- Upload Dokumen Opsional -->
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                    Upload Dokumen Pendukung <span class="font-normal text-slate-400">(Opsional — PDF, DOCX, JPG, PNG, ZIP maks 10MB)</span>
                </label>
                <label class="flex items-center justify-between px-3.5 py-2.5 rounded-lg border border-dashed border-slate-300 hover:border-slate-400 bg-slate-50/50 cursor-pointer transition">
                    <div class="flex items-center gap-2 text-xs text-slate-600 truncate">
                        <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                        </svg>
                        <span x-text="fileName || 'Pilih file dokumen lampiran...'" class="truncate"></span>
                    </div>
                    <span class="text-[11px] font-semibold text-indigo-600 shrink-0 ml-2">Pilih File</span>
                    <input type="file" name="attachment" class="hidden"
                           accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.zip"
                           @change="fileName = $event.target.files[0]?.name || ''">
                </label>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                <a href="{{ route('dashboard') }}" class="px-4 py-2 rounded-lg border border-slate-200 text-xs font-medium text-slate-600 hover:bg-slate-50">
                    Batal
                </a>
                <button type="submit" :disabled="submitting"
                        class="px-4 py-2 rounded-lg bg-slate-900 hover:bg-slate-800 text-white text-xs font-semibold transition disabled:opacity-60 disabled:cursor-not-allowed">
                    <span x-show="!submitting">Kirim Tiket</span>
                    <span x-show="submitting" x-cloak>Mengirim...</span>
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
