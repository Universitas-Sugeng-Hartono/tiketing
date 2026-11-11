<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Division;
use App\Models\Menu;
use App\Models\Role;
use App\Models\RolePriorityRoute;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class HierarchyController extends Controller
{
    public function index()
    {
        return redirect()->route('admin.categories');
    }

    /**
     * Buat Role Baru & Tentukan Hirarki Atasannya (parent_id)
     * Contoh: Superuser membuat role "Asisten Dosen" -> parent_id diarahkan ke "Dosen", atau "Dosen" -> ke "Kaprodi"
     */
    public function storeRole(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'division_id' => ['nullable', 'exists:divisions,id'],
            'parent_id' => ['nullable', 'exists:roles,id'],
            'badge_color' => ['required', 'string', 'in:indigo,emerald,amber,rose,violet,sky,teal,fuchsia'],
            'description' => ['nullable', 'string', 'max:300'],
            'can_create_ticket' => ['nullable', 'boolean'],
            'can_receive_ticket' => ['nullable', 'boolean'],
            'head_name' => ['nullable', 'string', 'max:150'],
            'head_email' => ['nullable', 'email', 'max:150', 'unique:users,email'],
            'password' => ['nullable', 'string', 'min:6'],
        ], [
            'head_email.unique' => 'Email pemegang role sudah digunakan oleh akun lain.',
        ]);

        $slug = Str::slug($validated['name']);
        if (Role::where('slug', $slug)->exists()) {
            $slug .= '-' . rand(10, 99);
        }

        $staffRows = $this->filledStaffRows($request->input('staff', []));
        $this->validateStaffRows($staffRows);
        $this->assertRouteIndexes($request->input('routes', []), $staffRows);

        $role = DB::transaction(function () use ($validated, $request, $slug, $staffRows) {
            $role = Role::create([
                'name' => $validated['name'],
                'slug' => $slug,
                'division_id' => ($validated['division_id'] ?? null) ?: null,
                'parent_id' => ($validated['parent_id'] ?? null) ?: null,
                'badge_color' => $validated['badge_color'],
                'icon' => 'user-group',
                'description' => $validated['description'] ?? null,
                'is_superuser' => false,
                'can_create_ticket' => $request->boolean('can_create_ticket', true),
                'can_receive_ticket' => $request->boolean('can_receive_ticket', true),
            ]);

            // Jika form menyediakan Pemegang Role / Kepala Unit
            if (!empty($validated['head_name']) && !empty($validated['head_email'])) {
                $headUser = User::create([
                    'name' => trim($validated['head_name']),
                    'email' => strtolower(trim($validated['head_email'])),
                    'password' => $request->filled('password') ? trim($request->password) : 'password',
                    'role_id' => $role->id,
                    'user_type' => 'admin',
                    'status_akademik' => 'Aktif',
                    'jabatan' => 'Kepala ' . $role->name,
                    'department' => $role->division?->name ?? $role->name,
                    'avatar_color' => $role->badge_color ?: 'indigo',
                ]);
                $role->update(['head_user_id' => $headUser->id]);

                if ($role->division_id) {
                    $division = $role->division;
                    if ($division) {
                        $lower = strtolower($role->name);
                        $pos = 'staff';
                        if (str_contains($lower, 'kepala') || str_contains($lower, 'head') || str_contains($lower, 'admin')) {
                            $pos = 'head';
                            if (!$division->head_role_id) {
                                $division->update(['head_role_id' => $role->id]);
                            }
                        } elseif (str_contains($lower, 'spv') || str_contains($lower, 'supervisor')) {
                            $pos = 'spv';
                            if (!$division->spv_role_id) {
                                $division->update(['spv_role_id' => $role->id]);
                            }
                        } else {
                            if (!$division->staff_role_id) {
                                $division->update(['staff_role_id' => $role->id]);
                            }
                        }
                        $division->members()->firstOrCreate(
                            ['user_id' => $headUser->id],
                            ['position' => $pos]
                        );
                    }
                }
            }

            // Fallback backward-compatible jika ada staff dikirim
            if ($staffRows !== []) {
                $created = $this->createStaffUsers($role, $staffRows);
                $this->storeRoutesByStaffIndex($role, $request->input('routes', []), $created);
                if (!$role->head_user_id) {
                    $this->assignHead($role, $request->input('head_index'), $created);
                }
            }

            return $role;
        });

        Role::recalculateLevels();

        // Berikan akses menu standar secara otomatis ke role baru (Beranda, Tiket Saya, Riwayat Tiket, + Inbox sesuai kapabilitas)
        $defaultSlugs = ['dashboard', 'tickets-my', 'tickets-history', 'tickets-workload', 'tickets-report', 'tickets-calendar'];
        if ($role->can_receive_ticket) {
            $defaultSlugs[] = 'tickets-inbox';
        }
        $defaultMenuIds = Menu::whereIn('slug', $defaultSlugs)->pluck('id');
        $role->menus()->sync($defaultMenuIds);

        $headInfo = $role->head ? " dengan Kepala Unit '{$role->head->name}' (login: {$role->head->email}, password: password)" : '';

        return back()->with('success', "Role baru '{$role->name}' berhasil dibuat{$headInfo}. Personel staf tambahan dapat diatur melalui tombol 'Kelola Staf'.");
    }

    public function storeDivision(Request $request)
    {
        if ($request->filled('head_user_id') && $request->filled('spv_user_id')) {
            return $this->storeDivisionLegacyPlot($request);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'badge_color' => ['required', 'string', 'in:indigo,emerald,amber,rose,violet,sky,teal,fuchsia'],
            'description' => ['nullable', 'string', 'max:300'],
        ]);

        $base = Str::slug($validated['name']);
        if ($base === '' || Division::where('slug', $base)->exists()) {
            $base .= '-'.rand(10, 99);
        }

        $division = Division::create([
            'name' => $validated['name'],
            'slug' => $base,
            'badge_color' => $validated['badge_color'],
            'description' => $validated['description'] ?? null,
        ]);

        return back()->with('success', "Divisi '{$division->name}' berhasil dibuat! Selanjutnya silakan tambahkan role dan akun untuk divisi ini.");
    }

    public function updateDivision(Request $request, Division $division)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'badge_color' => ['required', 'string', 'in:indigo,emerald,amber,rose,violet,sky,teal,fuchsia'],
            'description' => ['nullable', 'string', 'max:300'],
        ]);

        $division->update([
            'name' => $validated['name'],
            'badge_color' => $validated['badge_color'],
            'description' => $validated['description'] ?? null,
        ]);

        return back()->with('success', "Data Divisi '{$division->name}' berhasil diperbarui.");
    }

    public function destroyDivision(Division $division)
    {
        if ($division->categories()->exists()) {
            return back()->with('error', "Divisi '{$division->name}' tidak dapat dihapus karena masih digunakan oleh beberapa Kategori Layanan.");
        }

        $division->roles()->update(['division_id' => null]);
        $division->members()->delete();
        $name = $division->name;
        $division->delete();

        return back()->with('success', "Divisi '{$name}' berhasil dihapus.");
    }

    private function storeDivisionLegacyPlot(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'badge_color' => ['required', 'string', 'in:indigo,emerald,amber,rose,violet,sky,teal,fuchsia'],
            'description' => ['nullable', 'string', 'max:300'],
            'head_user_id' => ['required', 'exists:users,id'],
            'spv_user_id' => ['required', 'exists:users,id', 'different:head_user_id'],
            'staff_user_ids' => ['required', 'array', 'min:1'],
            'staff_user_ids.*' => ['nullable', 'exists:users,id'],
        ], [
            'head_user_id.required' => 'Pilih kepala dari role yang sudah ada.',
            'spv_user_id.required' => 'Pilih SPV dari role yang sudah ada.',
            'spv_user_id.different' => 'SPV harus orang yang berbeda dari kepala.',
            'staff_user_ids.required' => 'Pilih minimal satu staff.',
        ]);

        $staffIds = collect($validated['staff_user_ids'])
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($staffIds->isEmpty()) {
            throw ValidationException::withMessages([
                'staff_user_ids' => 'Pilih minimal satu staff.',
            ]);
        }

        $headId = (int) $validated['head_user_id'];
        $spvId = (int) $validated['spv_user_id'];
        if ($staffIds->contains($headId) || $staffIds->contains($spvId)) {
            throw ValidationException::withMessages([
                'staff_user_ids' => 'Staff tidak boleh sama dengan kepala atau SPV.',
            ]);
        }

        $users = User::with('role')->whereIn('id', $staffIds->concat([$headId, $spvId])->unique())->get()->keyBy('id');
        $headUser = $users->get($headId);
        $spvUser = $users->get($spvId);
        $staffUsers = $staffIds->map(fn (int $id) => $users->get($id));

        foreach ([$headUser, $spvUser, ...$staffUsers] as $person) {
            if (!$person?->role || $person->role->is_superuser) {
                throw ValidationException::withMessages([
                    'head_user_id' => 'Kepala, SPV, dan staff harus akun yang sudah punya role.',
                ]);
            }
        }

        $staffRoleIds = $staffUsers->pluck('role_id')->unique()->values();
        if ($staffRoleIds->count() !== 1) {
            throw ValidationException::withMessages([
                'staff_user_ids' => 'Semua staff harus berasal dari role yang sama.',
            ]);
        }

        $headRole = $headUser->role;
        $spvRole = $spvUser->role;
        $staffRoleId = (int) $staffRoleIds->first();

        $base = Str::slug($validated['name']);
        if ($base === '' || Division::where('slug', $base)->exists()) {
            $base .= '-'.rand(10, 99);
        }

        $division = DB::transaction(function () use ($validated, $base, $headUser, $spvUser, $staffUsers, $headRole, $spvRole, $staffRoleId) {
            $headRole->update(['head_user_id' => $headUser->id]);
            if ($spvRole->id !== $headRole->id) {
                $spvRole->update(['head_user_id' => $spvUser->id]);
            }

            $division = Division::create([
                'name' => $validated['name'],
                'slug' => $base,
                'badge_color' => $validated['badge_color'],
                'description' => $validated['description'] ?? null,
                'head_role_id' => $headRole->id,
                'spv_role_id' => $spvRole->id,
                'staff_role_id' => $staffRoleId,
            ]);

            $division->members()->create([
                'user_id' => $headUser->id,
                'position' => 'head',
            ]);
            $division->members()->create([
                'user_id' => $spvUser->id,
                'position' => 'spv',
            ]);
            foreach ($staffUsers as $staffUser) {
                $division->members()->create([
                    'user_id' => $staffUser->id,
                    'position' => 'staff',
                ]);
            }

            return $division;
        });

        $division->load(['headRole', 'spvRole', 'staffRole']);

        $staffNames = $staffUsers->pluck('name')->join(', ');

        return back()->with('success', "Divisi '{$division->name}' dibuat. Puncaknya {$headUser->name}. Plot layanan: {$staffNames} → {$spvUser->name} → {$headUser->name}.");
    }

    public function roleStaff(Role $role)
    {
        $role->load([
            'users' => fn ($query) => $query->orderBy('name')->limit(200),
            'priorityRoutes',
        ]);

        $routes = [];
        foreach (range(1, 8) as $level) {
            $routes[$level] = (string) ($role->priorityRoutes->firstWhere('priority_level', $level)?->user_id ?? '');
        }

        return response()->json([
            'id' => $role->id,
            'name' => $role->name,
            'actionUrl' => "/admin/hierarchy/roles/{$role->id}/staff",
            'staff' => $role->users->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'keahlian' => $user->keahlian,
            ])->values(),
            'routes' => $routes,
            'head_user_id' => $role->head_user_id ? (string) $role->head_user_id : '',
            'truncated' => $role->users()->count() > 200,
        ]);
    }

    public function syncStaff(Request $request, Role $role)
    {
        if ($role->is_superuser) {
            return back()->with('error', 'Role Superuser tidak diatur lewat form staff.');
        }

        $staffRows = $this->filledStaffRows($request->input('staff', []));
        $this->validateStaffRows($staffRows);

        DB::transaction(function () use ($request, $role, $staffRows) {
            $created = $this->createStaffUsers($role, $staffRows);
            $this->replaceRoutes($role, $request->input('routes', []), $created);
            if ($request->filled('head_user_id')) {
                $this->assignHead($role, $request->input('head_user_id'), $created);
            }
        });

        $count = count($staffRows);
        $msg = $count > 0
            ? "{$count} staf baru berhasil ditambahkan ke role '{$role->name}'! Akun langsung aktif dan dapat digunakan login (password: 'password' atau password kustom yang diinputkan)."
            : "Data staf dan pengaturan Kepala Unit role '{$role->name}' berhasil disimpan.";

        return back()->with('success', $msg);
    }

    public function removeStaff(Role $role, User $user)
    {
        if ($user->role_id !== $role->id) {
            return back()->with('error', 'Pengguna tidak terdaftar dalam role ini.');
        }

        if ($user->id === auth()->id()) {
            return back()->with('error', 'Anda tidak dapat menghapus akun Anda sendiri.');
        }

        // Jika user ini adalah kepala unit, alihkan ke staf lain atau kosongkan
        if ((int) $role->head_user_id === (int) $user->id) {
            $otherStaff = $role->users()->where('id', '!=', $user->id)->first();
            $role->update(['head_user_id' => $otherStaff?->id]);
            if ($otherStaff) {
                $otherStaff->update(['jabatan' => 'Kepala ' . $role->name]);
            }
        }

        // Hapus prioritas routing yang diarahkan ke user ini
        $role->priorityRoutes()->where('user_id', $user->id)->delete();

        $name = $user->name;
        if ($user->createdTickets()->doesntExist() && $user->assignedTickets()->doesntExist()) {
            $user->delete();
            return back()->with('success', "Staf '{$name}' berhasil dihapus dari sistem.");
        }

        $user->update(['role_id' => null, 'jabatan' => 'Nonaktif']);
        return back()->with('success', "Staf '{$name}' telah dilepaskan dari role '{$role->name}'.");
    }

    /**
     * Update Hirarki (Parent Role) & Konfigurasi Role Secara Dinamis
     */
    public function updateHierarchy(Request $request, Role $role)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'division_id' => ['nullable', 'exists:divisions,id'],
            'parent_id' => ['nullable', 'exists:roles,id'],
            'badge_color' => ['required', 'string', 'in:indigo,emerald,amber,rose,violet,sky,teal,fuchsia'],
            'description' => ['nullable', 'string', 'max:300'],
            'can_create_ticket' => ['nullable', 'boolean'],
            'can_receive_ticket' => ['nullable', 'boolean'],
        ]);

        $newParentId = $validated['parent_id'] ? (int) $validated['parent_id'] : null;

        if ($role->wouldCreateCycle($newParentId)) {
            return back()->with('error', "Gagal memperbarui hirarki: Menjadikan role tersebut sebagai atasan dari '{$role->name}' akan menimbulkan perulangan (circular loop)!");
        }

        $role->update([
            'name' => $validated['name'],
            'division_id' => ($validated['division_id'] ?? null) ?: null,
            'parent_id' => $newParentId,
            'badge_color' => $validated['badge_color'],
            'description' => $validated['description'],
            'can_create_ticket' => $request->boolean('can_create_ticket'),
            'can_receive_ticket' => $request->boolean('can_receive_ticket'),
        ]);

        Role::recalculateLevels();

        $parentName = $role->fresh()->parent?->name ?? 'Puncak Hirarki (Root)';

        return back()->with('success', "Hirarki & pengaturan Role '{$role->name}' berhasil diperbarui (Atasan: {$parentName}).");
    }

    public function destroyRole(Role $role)
    {
        if ($role->is_superuser) {
            return back()->with('error', 'Role Superuser tidak dapat dihapus.');
        }

        if (Ticket::where('current_role_id', $role->id)->orWhere('creator_role_id', $role->id)->exists()) {
            return back()->with('error', "Role '{$role->name}' tidak dapat dihapus karena masih terkait dengan riwayat tiket di sistem.");
        }

        // Hapus konfigurasi rute prioritas
        $role->priorityRoutes()->delete();

        // Lepaskan atau hapus akun pengguna pada role ini jika bukan superuser
        foreach ($role->users as $user) {
            if ($user->id !== auth()->id() && !$user->role?->is_superuser) {
                if ($user->createdTickets()->doesntExist() && $user->assignedTickets()->doesntExist()) {
                    $user->delete();
                } else {
                    $user->update(['role_id' => null, 'jabatan' => 'Nonaktif']);
                }
            }
        }

        // Sambungkan anak-anak dari role ini ke parent di atasnya agar rantai hirarki tidak terputus
        Role::where('parent_id', $role->id)->update(['parent_id' => $role->parent_id]);

        $name = $role->name;
        $role->delete();
        Role::recalculateLevels();

        return back()->with('success', "Role '{$name}' berhasil dihapus dari sistem.");
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $staff
     * @return array<int, array{name: string, email: string}>
     */
    private function filledStaffRows(array $staff): array
    {
        $rows = [];
        foreach ($staff as $index => $row) {
            if (!is_array($row)) {
                continue;
            }
            $name = trim((string) ($row['name'] ?? ''));
            $email = strtolower(trim((string) ($row['email'] ?? '')));
            if ($name === '' && $email === '') {
                continue;
            }
            $rows[(int) $index] = [
                'name' => $name,
                'email' => $email,
                'password' => trim((string) ($row['password'] ?? '')),
                'keahlian' => trim((string) ($row['keahlian'] ?? '')),
            ];
        }

        return $rows;
    }

    /**
     * @param  array<int, array{name: string, email: string}>  $staffRows
     */
    private function validateStaffRows(array $staffRows): void
    {
        if ($staffRows === []) {
            return;
        }

        $emails = collect($staffRows)->pluck('email');
        if ($emails->count() !== $emails->unique()->count()) {
            throw ValidationException::withMessages([
                'staff' => 'Email staff tidak boleh sama.',
            ]);
        }

        validator(['staff' => $staffRows], [
            'staff' => ['array', 'max:20'],
            'staff.*.name' => ['required', 'string', 'max:150'],
            'staff.*.email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'staff.*.keahlian' => ['nullable', 'string', 'max:120'],
        ], [
            'staff.*.name.required' => 'Nama staff wajib diisi.',
            'staff.*.email.required' => 'Email staff wajib diisi.',
            'staff.*.email.unique' => 'Email staff sudah terdaftar.',
        ])->validate();
    }

    /**
     * @param  array<int|string, mixed>  $routes
     * @param  array<int, array{name: string, email: string}>  $staffRows
     */
    private function assertRouteIndexes(array $routes, array $staffRows): void
    {
        foreach ($routes as $level => $index) {
            if ($index === null || $index === '') {
                continue;
            }
            $level = (int) $level;
            if ($level < 1 || $level > 8 || !is_numeric($index) || !isset($staffRows[(int) $index])) {
                throw ValidationException::withMessages([
                    'routes' => 'Setiap nomor prioritas (1–8) harus diarahkan ke staff yang namanya sudah diisi.',
                ]);
            }
        }
    }

    /**
     * @param  array<int, array{name: string, email: string}>  $staffRows
     * @return array<int, User>
     */
    private function createStaffUsers(Role $role, array $staffRows): array
    {
        $created = [];
        foreach ($staffRows as $index => $row) {
            $created[$index] = User::create([
                'name' => trim($row['name']),
                'email' => strtolower(trim($row['email'])),
                'password' => !empty($row['password']) ? trim($row['password']) : 'password',
                'role_id' => $role->id,
                'user_type' => 'admin',
                'status_akademik' => 'Aktif',
                'jabatan' => 'Staff ' . $role->name,
                'keahlian' => $row['keahlian'] !== '' ? $row['keahlian'] : null,
                'department' => $role->name,
                'avatar_color' => $role->badge_color ?: 'indigo',
            ]);
        }

        return $created;
    }

    /**
     * @param  array<int, User>  $created
     */
    private function assignHead(Role $role, mixed $target, array $created): void
    {
        if ($target === null || $target === '') {
            if (!$role->head_user_id && $created !== []) {
                $target = array_key_first($created);
            } else {
                return;
            }
        }

        $user = null;
        if (is_string($target) && str_starts_with($target, 'new-')) {
            $user = $created[(int) substr($target, 4)] ?? null;
        } elseif (is_numeric($target) && isset($created[(int) $target]) && !User::whereKey((int) $target)->where('role_id', $role->id)->exists()) {
            $user = $created[(int) $target];
        } elseif (is_numeric($target)) {
            $user = User::whereKey((int) $target)->where('role_id', $role->id)->first();
        }

        if (!$user) {
            throw ValidationException::withMessages([
                'head_user_id' => 'Kepala harus salah satu staff role ini.',
            ]);
        }

        // Reset jabatan kepala lama pada role ini jika ada
        User::where('role_id', $role->id)
            ->where('id', '!=', $user->id)
            ->where('jabatan', 'Kepala ' . $role->name)
            ->update(['jabatan' => 'Staff ' . $role->name]);

        $user->update(['jabatan' => 'Kepala ' . $role->name]);
        $role->update(['head_user_id' => $user->id]);
    }

    /**
     * @param  array<int|string, mixed>  $routes
     * @param  array<int, User>  $created
     */
    private function storeRoutesByStaffIndex(Role $role, array $routes, array $created): void
    {
        foreach ($routes as $level => $index) {
            if ($index === null || $index === '' || !isset($created[(int) $index])) {
                continue;
            }
            $level = (int) $level;
            if ($level < 1 || $level > 8) {
                continue;
            }
            RolePriorityRoute::create([
                'role_id' => $role->id,
                'priority_level' => $level,
                'user_id' => $created[(int) $index]->id,
            ]);
        }
    }

    /**
     * @param  array<int|string, mixed>  $routes
     * @param  array<int, User>  $created
     */
    private function replaceRoutes(Role $role, array $routes, array $created): void
    {
        $allowedIds = $role->users()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $rows = [];

        foreach ($routes as $level => $target) {
            if ($target === null || $target === '') {
                continue;
            }
            $level = (int) $level;
            if ($level < 1 || $level > 8) {
                throw ValidationException::withMessages([
                    'routes' => 'Nomor prioritas harus antara 1 dan 8.',
                ]);
            }

            $userId = null;
            if (is_string($target) && str_starts_with($target, 'new-')) {
                $index = (int) substr($target, 4);
                $userId = isset($created[$index]) ? (int) $created[$index]->id : null;
            } elseif (is_numeric($target)) {
                $userId = (int) $target;
            }

            if (!$userId || !in_array($userId, $allowedIds, true)) {
                throw ValidationException::withMessages([
                    'routes' => "Prioritas nomor {$level} harus diarahkan ke staff role ini.",
                ]);
            }

            $rows[$level] = $userId;
        }

        $role->priorityRoutes()->delete();
        foreach ($rows as $level => $userId) {
            RolePriorityRoute::create([
                'role_id' => $role->id,
                'priority_level' => $level,
                'user_id' => $userId,
            ]);
        }
    }
}
