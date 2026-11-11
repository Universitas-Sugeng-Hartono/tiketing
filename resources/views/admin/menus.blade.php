@extends('layouts.app')

@section('content')
@php
    $groupLabels = [
        'main' => 'Utama',
        'ticketing' => 'Tiketing',
        'management' => 'Manajemen',
    ];
    $groupBadgeColors = [
        'main' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
        'ticketing' => 'bg-amber-50 text-amber-700 border-amber-200',
        'management' => 'bg-teal-50 text-teal-700 border-teal-200',
    ];
    $availableIcons = [
        'home', 'ticket', 'document-check', 'inbox-arrow-down',
        'adjustments-horizontal', 'squares-plus', 'users', 'academic-cap',
        'user-group', 'building-library', 'clipboard-document-list', 'plus-circle', 'share'
    ];
    $availableRoutes = [
        'dashboard' => 'Beranda SuperApp (dashboard)',
        'tickets.my' => 'Tiket Saya (tickets.my)',
        'tickets.history' => 'Riwayat Tiket (tickets.history)',
        'tickets.inbox' => 'Tiket Masuk Role (tickets.inbox)',
        'tickets.calendar' => 'Kalender Tiket (tickets.calendar)',
        'tickets.report' => 'Laporan Bulanan (tickets.report)',
        'tickets.workload' => 'Pekerjaan Divisi (tickets.workload)',
        'admin.categories' => 'Kategori Layanan (admin.categories)',
        'admin.menus' => 'Manajemen Menu (admin.menus)',
        'admin.roles' => 'Manajemen Role (admin.roles)',
        'admin.users' => 'Manajemen Pengguna per Role (admin.users)',
    ];
    $defaultRoleIds = $roles->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
@endphp

<div x-data="{
    addMenuModal: false,
    editMenuModal: false,
    filterGroup: '',
    editForm: {
        id: null,
        actionUrl: '',
        name: '',
        parent_id: '',
        route_name: 'dashboard',
        group: 'main',
        icon: 'ticket',
        badge_text: '',
        description: '',
        sort_order: 1,
        show_in_sidebar: true,
        is_active: true,
        roles: []
    },
    openEditMenu(menu) {
        this.editForm.id = menu.id;
        this.editForm.actionUrl = menu.actionUrl;
        this.editForm.name = menu.name;
        this.editForm.parent_id = menu.parent_id || '';
        this.editForm.route_name = menu.route_name;
        this.editForm.group = menu.group;
        this.editForm.icon = menu.icon || 'ticket';
        this.editForm.badge_text = menu.badge_text || '';
        this.editForm.description = menu.description || '';
        this.editForm.sort_order = menu.sort_order;
        this.editForm.show_in_sidebar = menu.show_in_sidebar;
        this.editForm.is_active = menu.is_active;
        this.editForm.roles = [...(menu.roles || [])];
        this.editMenuModal = true;
    }
}">

    <!-- Header Ringkas -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
        <div>
            <h1 class="text-lg font-bold text-slate-900">Manajemen Menu</h1>
            <p class="text-xs text-slate-500">
                Atur struktur menu utama, sub-sidebar, urutan tampil, dan hak akses untuk setiap role.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <select x-model="filterGroup"
                    class="px-3 py-2 rounded-lg bg-white border border-slate-200 text-xs text-slate-700 focus:outline-none focus:border-slate-900">
                <option value="">Semua Grup Menu</option>
                <option value="main">Grup Utama</option>
                <option value="ticketing">Grup Tiketing</option>
                <option value="management">Grup Manajemen</option>
            </select>

            <button type="submit" form="matrix-form"
                    class="px-3.5 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold transition cursor-pointer">
                Simpan Hak Akses
            </button>

            <button type="button" @click="addMenuModal = true"
                    class="px-4 py-2 rounded-lg bg-slate-900 hover:bg-slate-800 text-white text-xs font-semibold transition cursor-pointer">
                + Tambah Menu
            </button>
        </div>
    </div>

    <!-- Tabel Menu & Matriks Hak Akses Role -->
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-2xs">
        <form id="matrix-form" action="{{ route('admin.menus.matrix') }}" method="POST">
            @csrf
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50/80 text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                            <th class="py-3 px-4 min-w-[230px]">Menu & Sub-Sidebar</th>
                            <th class="py-3 px-3">Grup</th>
                            <th class="py-3 px-3">Target Route / URL</th>
                            <th class="py-3 px-2 text-center w-16">Urutan</th>
                            @foreach($roles as $role)
                                <th class="py-3 px-2.5 text-center whitespace-nowrap">{{ $role->name }}</th>
                            @endforeach
                            <th class="py-3 px-4 text-right w-28">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($menus as $menu)
                            @php
                                $isSubMenu = !is_null($menu->parent_id);
                                $editPayload = [
                                    'id' => $menu->id,
                                    'actionUrl' => route('admin.menus.update', $menu),
                                    'name' => $menu->name,
                                    'parent_id' => $menu->parent_id ? (string) $menu->parent_id : '',
                                    'route_name' => $menu->route_name,
                                    'group' => $menu->group,
                                    'icon' => $menu->icon,
                                    'badge_text' => $menu->badge_text,
                                    'description' => $menu->description,
                                    'sort_order' => (int) $menu->sort_order,
                                    'show_in_sidebar' => (bool) $menu->show_in_sidebar,
                                    'is_active' => (bool) $menu->is_active,
                                    'roles' => $menu->roles->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
                                ];
                            @endphp
                            <tr x-show="!filterGroup || filterGroup === '{{ $menu->group }}'"
                                class="hover:bg-slate-50/70 transition {{ $isSubMenu ? 'bg-slate-50/30' : '' }}">
                                <!-- 1. Nama Menu / Sub-Sidebar -->
                                <td class="py-3 px-4">
                                    <div class="flex items-center gap-2 {{ $isSubMenu ? 'pl-5' : '' }}">
                                        @if($isSubMenu)
                                            <span class="text-slate-300 font-bold">↳</span>
                                        @endif
                                        <span class="w-6 h-6 rounded-md {{ $isSubMenu ? 'bg-white border border-slate-200 text-slate-500' : 'bg-slate-100 text-slate-700' }} flex items-center justify-center shrink-0">
                                            @include('partials.icon', ['name' => $menu->icon, 'class' => 'w-3.5 h-3.5'])
                                        </span>
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-1.5">
                                                <span class="{{ $isSubMenu ? 'font-medium text-slate-700' : 'font-semibold text-slate-900' }}">
                                                    {{ $menu->name }}
                                                </span>
                                                @if(!$menu->is_active)
                                                    <span class="px-1.5 py-0.5 rounded text-[9px] font-semibold bg-rose-50 text-rose-600">Nonaktif</span>
                                                @elseif(!$menu->show_in_sidebar)
                                                    <span class="px-1.5 py-0.5 rounded text-[9px] font-semibold bg-slate-100 text-slate-500">Hidden</span>
                                                @endif
                                            </div>
                                            @if($isSubMenu && $menu->parent)
                                                <span class="text-[10px] text-slate-400 block">Sub-menu dari {{ $menu->parent->name }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </td>

                                <!-- 2. Grup -->
                                <td class="py-3 px-3 whitespace-nowrap">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-medium border {{ $groupBadgeColors[$menu->group] ?? 'bg-slate-100 text-slate-600 border-slate-200' }}">
                                        {{ $groupLabels[$menu->group] ?? ucfirst($menu->group) }}
                                    </span>
                                </td>

                                <!-- 3. Route / URL -->
                                <td class="py-3 px-3 font-mono text-[11px] text-slate-500 whitespace-nowrap">
                                    {{ $menu->route_name }}
                                </td>

                                <!-- 4. Urutan -->
                                <td class="py-3 px-2 text-center text-slate-500 font-medium">
                                    {{ $menu->sort_order }}
                                </td>

                                <!-- 5. Matriks Hak Akses per Role -->
                                @foreach($roles as $role)
                                    <td class="py-3 px-2.5 text-center">
                                        <input type="checkbox"
                                               name="matrix[{{ $menu->id }}][]"
                                               value="{{ $role->id }}"
                                               {{ $menu->roles->contains('id', $role->id) ? 'checked' : '' }}
                                               class="w-4 h-4 rounded border-slate-300 text-indigo-600 cursor-pointer">
                                    </td>
                                @endforeach

                                <!-- 6. Aksi -->
                                <td class="py-3 px-4 text-right whitespace-nowrap">
                                    <div class="inline-flex items-center gap-1.5">
                                        <button type="button"
                                                @click="openEditMenu({{ Js::from($editPayload) }})"
                                                class="px-2.5 py-1 rounded-lg border border-slate-200 hover:bg-slate-100 text-slate-700 font-medium transition cursor-pointer">
                                            Edit
                                        </button>
                                        @if(!in_array($menu->slug, ['dashboard', 'admin-menus'], true))
                                            <button type="submit"
                                                    form="del-menu-{{ $menu->id }}"
                                                    onclick="return confirm('Hapus menu {{ addslashes($menu->name) }}?')"
                                                    class="px-2 py-1 rounded-lg text-rose-600 hover:bg-rose-50 font-medium transition cursor-pointer">
                                                Hapus
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </form>

        @foreach($menus as $menu)
            @if(!in_array($menu->slug, ['dashboard', 'admin-menus'], true))
                <form id="del-menu-{{ $menu->id }}" action="{{ route('admin.menus.destroy', $menu) }}" method="POST" class="hidden">
                    @csrf
                    @method('DELETE')
                </form>
            @endif
        @endforeach
    </div>

    <!-- Datalist Route Bawaan untuk Fleksibilitas Pilih Route atau Ketik URL Kustom -->
    <datalist id="system-routes-list">
        @foreach($availableRoutes as $rKey => $rLabel)
            <option value="{{ $rKey }}">{{ $rLabel }}</option>
        @endforeach
    </datalist>

    <!-- MODAL 1: Tambah Menu / Sub-Sidebar Baru -->
    <div x-show="addMenuModal" x-cloak
         @keydown.escape.window="addMenuModal = false"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs">
        <div @click.outside="addMenuModal = false"
             x-data="{ parentId: '' }"
             class="bg-white rounded-2xl border border-slate-200 shadow-xl max-w-lg w-full max-h-[90vh] flex flex-col overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <h3 class="text-sm font-bold text-slate-900">Tambah Menu Baru</h3>
                <button type="button" @click="addMenuModal = false" class="p-1 rounded-lg text-slate-400 hover:text-slate-700">✕</button>
            </div>

            <form action="{{ route('admin.menus.store') }}" method="POST" class="p-6 space-y-4 overflow-y-auto">
                @csrf
                <input type="hidden" name="color" value="indigo">

                <div class="grid grid-cols-1 sm:grid-cols-12 gap-3">
                    <div class="sm:col-span-7">
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Menu</label>
                        <input type="text" name="name" required
                               placeholder="Contoh: Laporan Akademik"
                               class="w-full px-3 py-2 rounded-lg border border-slate-200 text-xs focus:outline-none focus:border-slate-900">
                    </div>
                    <div class="sm:col-span-5">
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Posisi Menu</label>
                        <select name="parent_id" x-model="parentId"
                                class="w-full px-3 py-2 rounded-lg border border-slate-200 text-xs bg-white focus:outline-none focus:border-slate-900">
                            <option value="">Menu Utama (Induk)</option>
                            @foreach($parentMenus as $pm)
                                <option value="{{ $pm->id }}">Sub-Menu: {{ $pm->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-12 gap-3">
                    <div class="sm:col-span-8">
                        <label class="block text-xs font-semibold text-slate-700 mb-1">
                            Target Route atau URL <span class="font-normal text-slate-400">(Pilih atau ketik bebas)</span>
                        </label>
                        <input type="text" name="route_name" list="system-routes-list" required
                               value="tickets.my"
                               placeholder="Contoh: tickets.my atau /laporan"
                               class="w-full px-3 py-2 rounded-lg border border-slate-200 text-xs font-mono focus:outline-none focus:border-slate-900">
                    </div>
                    <div class="sm:col-span-4">
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Urutan</label>
                        <input type="number" name="sort_order" value="{{ $menus->count() + 1 }}" min="0" required
                               class="w-full px-3 py-2 rounded-lg border border-slate-200 text-xs focus:outline-none focus:border-slate-900">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Grup Sidebar</label>
                        <select name="group" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-xs bg-white">
                            <option value="main">Utama</option>
                            <option value="ticketing">Tiketing</option>
                            <option value="management">Manajemen</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Ikon</label>
                        <select name="icon" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-xs bg-white">
                            @foreach($availableIcons as $ic)
                                <option value="{{ $ic }}">{{ $ic }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Badge <span class="font-normal text-slate-400">(Opsional)</span></label>
                        <input type="text" name="badge_text" placeholder="Contoh: Baru"
                               class="w-full px-3 py-2 rounded-lg border border-slate-200 text-xs">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Hak Akses Role</label>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 p-3 rounded-lg bg-slate-50 border border-slate-200/70">
                        @foreach($roles as $r)
                            <label class="inline-flex items-center gap-1.5 text-xs text-slate-700 cursor-pointer">
                                <input type="checkbox" name="roles[]" value="{{ $r->id }}" checked
                                       class="rounded border-slate-300 text-indigo-600">
                                <span class="truncate">{{ $r->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="flex items-center gap-5 pt-1">
                    <label class="inline-flex items-center gap-2 text-xs text-slate-700 cursor-pointer">
                        <input type="checkbox" name="show_in_sidebar" value="1" checked class="rounded border-slate-300 text-indigo-600">
                        <span>Tampilkan di Sidebar</span>
                    </label>
                    <label class="inline-flex items-center gap-2 text-xs text-slate-700 cursor-pointer">
                        <input type="checkbox" name="is_active" value="1" checked class="rounded border-slate-300 text-indigo-600">
                        <span>Menu Aktif</span>
                    </label>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                    <button type="button" @click="addMenuModal = false"
                            class="px-4 py-2 rounded-lg border border-slate-200 text-xs font-medium text-slate-600 hover:bg-slate-50">
                        Batal
                    </button>
                    <button type="submit"
                            class="px-4 py-2 rounded-lg bg-slate-900 hover:bg-slate-800 text-white text-xs font-semibold">
                        Simpan Menu
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 2: Edit Menu -->
    <div x-show="editMenuModal" x-cloak
         @keydown.escape.window="editMenuModal = false"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs">
        <div @click.outside="editMenuModal = false"
             class="bg-white rounded-2xl border border-slate-200 shadow-xl max-w-lg w-full max-h-[90vh] flex flex-col overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <h3 class="text-sm font-bold text-slate-900">Edit Menu</h3>
                <button type="button" @click="editMenuModal = false" class="p-1 rounded-lg text-slate-400 hover:text-slate-700">✕</button>
            </div>

            <form :action="editForm.actionUrl" method="POST" class="p-6 space-y-4 overflow-y-auto">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 sm:grid-cols-12 gap-3">
                    <div class="sm:col-span-7">
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Menu</label>
                        <input type="text" name="name" x-model="editForm.name" required
                               class="w-full px-3 py-2 rounded-lg border border-slate-200 text-xs focus:outline-none focus:border-slate-900">
                    </div>
                    <div class="sm:col-span-5">
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Posisi Menu</label>
                        <select name="parent_id" x-model="editForm.parent_id"
                                class="w-full px-3 py-2 rounded-lg border border-slate-200 text-xs bg-white focus:outline-none focus:border-slate-900">
                            <option value="">Menu Utama (Induk)</option>
                            @foreach($parentMenus as $pm)
                                <option value="{{ $pm->id }}" x-show="editForm.id !== {{ $pm->id }}">Sub-Menu: {{ $pm->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-12 gap-3">
                    <div class="sm:col-span-8">
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Target Route atau URL</label>
                        <input type="text" name="route_name" x-model="editForm.route_name" list="system-routes-list" required
                               class="w-full px-3 py-2 rounded-lg border border-slate-200 text-xs font-mono focus:outline-none focus:border-slate-900">
                    </div>
                    <div class="sm:col-span-4">
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Urutan</label>
                        <input type="number" name="sort_order" x-model="editForm.sort_order" min="0" required
                               class="w-full px-3 py-2 rounded-lg border border-slate-200 text-xs focus:outline-none focus:border-slate-900">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Grup Sidebar</label>
                        <select name="group" x-model="editForm.group" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-xs bg-white">
                            <option value="main">Utama</option>
                            <option value="ticketing">Tiketing</option>
                            <option value="management">Manajemen</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Ikon</label>
                        <select name="icon" x-model="editForm.icon" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-xs bg-white">
                            @foreach($availableIcons as $ic)
                                <option value="{{ $ic }}">{{ $ic }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Badge</label>
                        <input type="text" name="badge_text" x-model="editForm.badge_text"
                               class="w-full px-3 py-2 rounded-lg border border-slate-200 text-xs">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Hak Akses Role</label>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 p-3 rounded-lg bg-slate-50 border border-slate-200/70">
                        @foreach($roles as $r)
                            <label class="inline-flex items-center gap-1.5 text-xs text-slate-700 cursor-pointer">
                                <input type="checkbox" name="roles[]" :value="{{ $r->id }}" x-model.number="editForm.roles"
                                       class="rounded border-slate-300 text-indigo-600">
                                <span class="truncate">{{ $r->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="flex items-center gap-5 pt-1">
                    <label class="inline-flex items-center gap-2 text-xs text-slate-700 cursor-pointer">
                        <input type="checkbox" name="show_in_sidebar" value="1" x-model="editForm.show_in_sidebar"
                               class="rounded border-slate-300 text-indigo-600">
                        <span>Tampilkan di Sidebar</span>
                    </label>
                    <label class="inline-flex items-center gap-2 text-xs text-slate-700 cursor-pointer">
                        <input type="checkbox" name="is_active" value="1" x-model="editForm.is_active"
                               class="rounded border-slate-300 text-indigo-600">
                        <span>Menu Aktif</span>
                    </label>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                    <button type="button" @click="editMenuModal = false"
                            class="px-4 py-2 rounded-lg border border-slate-200 text-xs font-medium text-slate-600 hover:bg-slate-50">
                        Batal
                    </button>
                    <button type="submit"
                            class="px-4 py-2 rounded-lg bg-slate-900 hover:bg-slate-800 text-white text-xs font-semibold">
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
