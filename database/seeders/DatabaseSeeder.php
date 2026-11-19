<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketLog;
use App\Models\TicketProblem;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Buat Role & Struktur Hirarki (Self-referencing parent_id)
        $superuserRole = Role::create([
            'name' => 'Superuser',
            'slug' => 'superuser',
            'parent_id' => null,
            'level' => 0,
            'badge_color' => 'rose',
            'icon' => 'shield-check',
            'description' => 'Administrator tertinggi yang mengelola seluruh menu dinamis, role, pohon hirarki, dan konfigurasi sistem.',
            'is_superuser' => true,
            'can_create_ticket' => true,
            'can_receive_ticket' => true,
        ]);

        $dekanRole = Role::create([
            'name' => 'Dekan',
            'slug' => 'dekan',
            'parent_id' => null,
            'level' => 0,
            'badge_color' => 'violet',
            'icon' => 'building-library',
            'description' => 'Pimpinan tertinggi fakultas. Menerima eskalasi strategis atau approval akhir dari Kaprodi.',
            'is_superuser' => false,
            'can_create_ticket' => true,
            'can_receive_ticket' => true,
        ]);

        $kaprodiRole = Role::create([
            'name' => 'Kaprodi',
            'slug' => 'kaprodi',
            'parent_id' => $dekanRole->id,
            'level' => 1,
            'badge_color' => 'amber',
            'icon' => 'academic-cap',
            'description' => 'Ketua Program Studi. Hirarki di atas Dosen dan berada di bawah Dekan.',
            'is_superuser' => false,
            'can_create_ticket' => true,
            'can_receive_ticket' => true,
        ]);

        $dosenRole = Role::create([
            'name' => 'Dosen',
            'slug' => 'dosen',
            'parent_id' => $kaprodiRole->id,
            'level' => 2,
            'badge_color' => 'emerald',
            'icon' => 'user-group',
            'description' => 'Dosen Wali & Pembimbing Akademik. Menerima tiket langsung dari Mahasiswa dan memiliki hirarki ke Kaprodi.',
            'is_superuser' => false,
            'can_create_ticket' => true,
            'can_receive_ticket' => true,
        ]);

        $mahasiswaRole = Role::create([
            'name' => 'Mahasiswa',
            'slug' => 'mahasiswa',
            'parent_id' => $dosenRole->id,
            'level' => 3,
            'badge_color' => 'indigo',
            'icon' => 'user',
            'description' => 'Mahasiswa aktif yang mengajukan tiket akademik, bimbingan, surat, atau keluhan ke Dosen.',
            'is_superuser' => false,
            'can_create_ticket' => true,
            'can_receive_ticket' => false,
        ]);

        $yayasanRole = Role::create([
            'name' => 'Yayasan',
            'slug' => 'yayasan',
            'parent_id' => null,
            'level' => 0,
            'badge_color' => 'teal',
            'icon' => 'building-library',
            'description' => 'Pengurus Yayasan yang memberikan persetujuan akhir pengadaan dana dan anggaran strategis.',
            'is_superuser' => false,
            'can_create_ticket' => true,
            'can_receive_ticket' => true,
        ]);

        $akademikRole = Role::create([
            'name' => 'Akademik',
            'slug' => 'akademik',
            'parent_id' => $yayasanRole->id,
            'level' => 1,
            'badge_color' => 'sky',
            'icon' => 'clipboard-document-list',
            'description' => 'Bagian Akademik & Kemahasiswaan yang memverifikasi pengajuan layanan serta proposal kegiatan mahasiswa.',
            'is_superuser' => false,
            'can_create_ticket' => true,
            'can_receive_ticket' => true,
        ]);

        // Role Hirarki Karyawan IT: Staff -> Admin IT -> Supervisor IT
        $supervisorItRole = Role::create([
            'name' => 'Supervisor IT',
            'slug' => 'supervisor-it',
            'parent_id' => $yayasanRole->id,
            'level' => 1,
            'badge_color' => 'violet',
            'icon' => 'shield-check',
            'description' => 'Supervisor & Pimpinan Unit IT. Menerima eskalasi/persetujuan dari Admin IT serta mengawasi SLA pengerjaan infrastruktur IT.',
            'is_superuser' => false,
            'can_create_ticket' => true,
            'can_receive_ticket' => true,
        ]);

        $adminItRole = Role::create([
            'name' => 'Admin IT',
            'slug' => 'admin-it',
            'parent_id' => $supervisorItRole->id,
            'level' => 2,
            'badge_color' => 'sky',
            'icon' => 'adjustments-horizontal',
            'description' => 'Unit Helpdesk & Teknis Admin IT. Menerima tiket gangguan, mendistribusikan ke teknisi sesuai keahlian, dan memantau progres pengerjaan.',
            'is_superuser' => false,
            'can_create_ticket' => true,
            'can_receive_ticket' => true,
        ]);

        $staffRole = Role::create([
            'name' => 'Staff',
            'slug' => 'staff',
            'parent_id' => $adminItRole->id,
            'level' => 3,
            'badge_color' => 'emerald',
            'icon' => 'user-group',
            'description' => 'Staff Operasional & Tenaga Kependidikan yang mengajukan atau menangani operasional harian unit kerja.',
            'is_superuser' => false,
            'can_create_ticket' => true,
            'can_receive_ticket' => true,
        ]);

        Role::recalculateLevels();

        // 2. Buat Users untuk masing-masing Role
        $superuser = User::create([
            'name' => 'Super Admin Kampus',
            'email' => 'superuser@kampus.ac.id',
            'password' => Hash::make('password'),
            'role_id' => $superuserRole->id,
            'user_type' => 'admin',
            'identity_number' => 'SUP-001',
            'department' => 'Direktorat Sistem Informasi',
            'jabatan' => 'Superuser Sistem',
            'avatar_color' => 'rose',
        ]);

        $dekan = User::create([
            'name' => 'Prof. Dr. Hendra Wijaya, M.Kom.',
            'email' => 'dekan@kampus.ac.id',
            'password' => Hash::make('password'),
            'role_id' => $dekanRole->id,
            'user_type' => 'pengajar',
            'identity_number' => 'NIP-19720411',
            'department' => 'Fakultas Teknologi, Hukum dan Bisnis',
            'fakultas' => 'FAKULTAS TEKNOLOGI, HUKUM DAN BISNIS',
            'jabatan' => 'Dekan Fakultas',
            'avatar_color' => 'violet',
        ]);

        $kaprodi = User::create([
            'name' => 'Dr. Rina Kartika, S.T., M.T.',
            'email' => 'kaprodi@kampus.ac.id',
            'password' => Hash::make('password'),
            'role_id' => $kaprodiRole->id,
            'supervisor_id' => $dekan->id,
            'user_type' => 'pengajar',
            'identity_number' => 'NIDN-0412088501',
            'department' => 'Informatika',
            'fakultas' => 'FAKULTAS TEKNOLOGI, HUKUM DAN BISNIS',
            'jabatan' => 'Kaprodi Informatika',
            'avatar_color' => 'amber',
        ]);

        $dosen = User::create([
            'name' => 'Budi Santoso, S.Kom., M.Cs.',
            'email' => 'dosen@kampus.ac.id',
            'password' => Hash::make('password'),
            'role_id' => $dosenRole->id,
            'supervisor_id' => $kaprodi->id,
            'user_type' => 'pengajar',
            'identity_number' => 'NIDN-0425099002',
            'department' => 'Informatika',
            'fakultas' => 'FAKULTAS TEKNOLOGI, HUKUM DAN BISNIS',
            'jabatan' => 'Dosen Tetap',
            'avatar_color' => 'emerald',
        ]);

        $yayasanUser = User::create([
            'name' => 'H. Darmawan Putra, S.E., M.M.',
            'email' => 'yayasan@kampus.ac.id',
            'password' => Hash::make('password'),
            'role_id' => $yayasanRole->id,
            'user_type' => 'pengajar',
            'identity_number' => 'YYS-001',
            'department' => 'Yayasan Pendidikan USH',
            'fakultas' => 'YAYASAN',
            'jabatan' => 'Ketua Bidang Keuangan Yayasan',
            'avatar_color' => 'teal',
        ]);

        $akademikUser = User::create([
            'name' => 'Ratna Dewi, S.Pd., M.Si.',
            'email' => 'akademik@kampus.ac.id',
            'password' => Hash::make('password'),
            'role_id' => $akademikRole->id,
            'supervisor_id' => $yayasanUser->id,
            'user_type' => 'pengajar',
            'identity_number' => 'AKD-001',
            'department' => 'Biro Administrasi Akademik & Kemahasiswaan',
            'fakultas' => 'REKTORAT & AKADEMIK',
            'jabatan' => 'Kepala Biro Akademik',
            'avatar_color' => 'sky',
        ]);

        $mahasiswa1 = User::create([
            'name' => 'Andi Pratama',
            'email' => 'mahasiswa@kampus.ac.id',
            'password' => Hash::make('password'),
            'role_id' => $mahasiswaRole->id,
            'supervisor_id' => $dosen->id,
            'user_type' => 'mahasiswa',
            'identity_number' => '2310511042',
            'department' => 'Informatika',
            'fakultas' => 'FAKULTAS TEKNOLOGI, HUKUM DAN BISNIS',
            'angkatan' => 2023,
            'semester' => 5,
            'status_akademik' => 'Aktif',
            'avatar_color' => 'indigo',
        ]);

        $mahasiswa2 = User::create([
            'name' => 'Siti Nabila Putri',
            'email' => 'siti@kampus.ac.id',
            'password' => Hash::make('password'),
            'role_id' => $mahasiswaRole->id,
            'supervisor_id' => $dosen->id,
            'user_type' => 'mahasiswa',
            'identity_number' => '2410511018',
            'department' => 'Informatika',
            'fakultas' => 'FAKULTAS TEKNOLOGI, HUKUM DAN BISNIS',
            'angkatan' => 2024,
            'semester' => 3,
            'status_akademik' => 'Aktif',
            'avatar_color' => 'sky',
        ]);

        // Data Dummy Karyawan (Kasus: Supervisor IT, Admin IT, dan Staff)
        $supervisorItUser = User::create([
            'name' => 'Ir. Hendra Pratama, M.T.I.',
            'email' => 'supervisor.it@kampus.ac.id',
            'password' => Hash::make('password'),
            'role_id' => $supervisorItRole->id,
            'supervisor_id' => $yayasanUser->id,
            'user_type' => 'karyawan',
            'identity_number' => 'KRY-IT-001',
            'department' => 'Direktorat Teknologi Informasi (DTI)',
            'fakultas' => 'DIREKTORAT IT & INFRASTRUKTUR',
            'jabatan' => 'Supervisor IT & Infrastruktur',
            'keahlian' => 'Manajemen Infrastruktur & Keamanan Sistem',
            'avatar_color' => 'violet',
        ]);

        // Tetapkan Ir. Hendra Pratama sebagai Kepala Role Supervisor IT (menerima request IT pertama, menetapkan prioritas, poin pekerjaan %, & mendeliver ke Staff IT)
        $supervisorItRole->update(['head_user_id' => $supervisorItUser->id]);

        $adminItLead = User::create([
            'name' => 'Rizky Aditya, S.Kom., M.Cs.',
            'email' => 'admin.it@kampus.ac.id',
            'password' => Hash::make('password'),
            'role_id' => $adminItRole->id,
            'supervisor_id' => $supervisorItUser->id,
            'user_type' => 'karyawan',
            'identity_number' => 'KRY-IT-002',
            'department' => 'Direktorat Teknologi Informasi (DTI)',
            'fakultas' => 'DIREKTORAT IT & INFRASTRUKTUR',
            'jabatan' => 'Admin IT & Helpdesk',
            'keahlian' => 'Administrasi Sistem & Akun Kampus',
            'avatar_color' => 'sky',
        ]);

        $adminItRole->update(['head_user_id' => $adminItLead->id]);

        $adminItJaringan = User::create([
            'name' => 'Fajar Nugroho, S.T.',
            'email' => 'fajar.jaringan@kampus.ac.id',
            'password' => Hash::make('password'),
            'role_id' => $staffRole->id,
            'supervisor_id' => $supervisorItUser->id,
            'user_type' => 'karyawan',
            'identity_number' => 'KRY-IT-003',
            'department' => 'Direktorat Teknologi Informasi (DTI)',
            'fakultas' => 'DIREKTORAT IT & INFRASTRUKTUR',
            'jabatan' => 'Staff IT — Spesialis Jaringan',
            'keahlian' => 'Jaringan & Server',
            'avatar_color' => 'emerald',
        ]);

        $adminItHardware = User::create([
            'name' => 'Bagas Setiawan, A.Md.Kom.',
            'email' => 'bagas.hardware@kampus.ac.id',
            'password' => Hash::make('password'),
            'role_id' => $staffRole->id,
            'supervisor_id' => $supervisorItUser->id,
            'user_type' => 'karyawan',
            'identity_number' => 'KRY-IT-004',
            'department' => 'Direktorat Teknologi Informasi (DTI)',
            'fakultas' => 'DIREKTORAT IT & INFRASTRUKTUR',
            'jabatan' => 'Staff IT — Spesialis Hardware',
            'keahlian' => 'Perangkat keras',
            'avatar_color' => 'emerald',
        ]);

        $adminItAplikasi = User::create([
            'name' => 'Nadia Kusuma, S.Kom.',
            'email' => 'nadia.aplikasi@kampus.ac.id',
            'password' => Hash::make('password'),
            'role_id' => $staffRole->id,
            'supervisor_id' => $supervisorItUser->id,
            'user_type' => 'karyawan',
            'identity_number' => 'KRY-IT-005',
            'department' => 'Direktorat Teknologi Informasi (DTI)',
            'fakultas' => 'DIREKTORAT IT & INFRASTRUKTUR',
            'jabatan' => 'Staff IT — Spesialis Aplikasi',
            'keahlian' => 'Aplikasi & SIAKAD',
            'avatar_color' => 'emerald',
        ]);

        $staffItLab = User::create([
            'name' => 'Dimas Wahyu, S.Kom.',
            'email' => 'staff.it@kampus.ac.id',
            'password' => Hash::make('password'),
            'role_id' => $staffRole->id,
            'supervisor_id' => $supervisorItUser->id,
            'user_type' => 'karyawan',
            'identity_number' => 'KRY-IT-006',
            'department' => 'Unit Laboratorium & Operasional IT',
            'fakultas' => 'DIREKTORAT IT & INFRASTRUKTUR',
            'jabatan' => 'Staff Operasional & Laboratorium IT',
            'keahlian' => 'Operasional Lab & Perangkat Lunak',
            'avatar_color' => 'emerald',
        ]);

        $staffUmum = User::create([
            'name' => 'Rina Wulandari, S.E.',
            'email' => 'staff.umum@kampus.ac.id',
            'password' => Hash::make('password'),
            'role_id' => $staffRole->id,
            'supervisor_id' => $supervisorItUser->id,
            'user_type' => 'karyawan',
            'identity_number' => 'KRY-ADM-007',
            'department' => 'Bagian Umum & Sarana Prasarana',
            'fakultas' => 'DIREKTORAT UMUM & SDM',
            'jabatan' => 'Staff Administrasi & Inventaris IT',
            'keahlian' => 'Administrasi & Inventaris Perangkat',
            'avatar_color' => 'emerald',
        ]);

        // 3. Buat Menu Dinamis (SuperApp Launcher & Sidebar beserta Sub-Sidebar Manajemen Pengguna)
        $allActiveRoles = [
            $superuserRole->id,
            $dekanRole->id,
            $kaprodiRole->id,
            $dosenRole->id,
            $yayasanRole->id,
            $akademikRole->id,
            $supervisorItRole->id,
            $adminItRole->id,
            $staffRole->id,
            $mahasiswaRole->id,
        ];
        $allReceiverRoles = [
            $superuserRole->id,
            $dekanRole->id,
            $kaprodiRole->id,
            $dosenRole->id,
            $yayasanRole->id,
            $akademikRole->id,
            $supervisorItRole->id,
            $adminItRole->id,
            $staffRole->id,
        ];

        $menusData = [
            [
                'name' => 'Beranda SuperApp',
                'slug' => 'dashboard',
                'route_name' => 'dashboard',
                'icon' => 'home',
                'color' => 'indigo',
                'group' => 'main',
                'badge_text' => 'Utama',
                'description' => 'Pusat kendali utama, statistik layanan, dan peluncur cepat SuperApp.',
                'show_in_sidebar' => true,
                'show_in_launcher' => true,
                'sort_order' => 1,
                'roles' => $allActiveRoles,
            ],
            [
                'name' => 'Tiket Saya',
                'slug' => 'tickets-my',
                'route_name' => 'tickets.my',
                'icon' => 'ticket',
                'color' => 'indigo',
                'group' => 'ticketing',
                'badge_text' => 'Aktif & Buat Baru',
                'description' => 'Daftar tiket yang sedang diproses beserta tombol Buat Tiket Baru.',
                'show_in_sidebar' => true,
                'show_in_launcher' => true,
                'sort_order' => 2,
                'roles' => $allActiveRoles,
            ],
            [
                'name' => 'Riwayat Tiket',
                'slug' => 'tickets-history',
                'route_name' => 'tickets.history',
                'icon' => 'document-check',
                'color' => 'emerald',
                'group' => 'ticketing',
                'badge_text' => 'Sudah Approve',
                'description' => 'Riwayat tiket yang sudah berstatus Approve / Selesai beserta preview dokumen.',
                'show_in_sidebar' => true,
                'show_in_launcher' => true,
                'sort_order' => 3,
                'roles' => $allActiveRoles,
            ],
            [
                'name' => 'Tiket Masuk Role',
                'slug' => 'tickets-inbox',
                'route_name' => 'tickets.inbox',
                'icon' => 'inbox-arrow-down',
                'color' => 'amber',
                'group' => 'ticketing',
                'badge_text' => 'Inbox Hirarki',
                'description' => 'Daftar tiket masuk dari role di bawah Anda untuk diatur prioritasnya, disetujui, atau dieskalasi.',
                'show_in_sidebar' => true,
                'show_in_launcher' => true,
                'sort_order' => 4,
                'roles' => $allReceiverRoles,
            ],
            [
                'name' => 'Staff Progres',
                'slug' => 'tickets-staff-progress',
                'route_name' => 'tickets.staff-progress',
                'icon' => 'chart-bar',
                'color' => 'indigo',
                'group' => 'ticketing',
                'badge_text' => 'Progres Tim',
                'description' => 'Monitoring progres pengerjaan, beban tiket, dan status kendala setiap staff.',
                'show_in_sidebar' => true,
                'show_in_launcher' => true,
                'sort_order' => 5,
                'roles' => $allReceiverRoles,
            ],
            [
                'name' => 'Kalender Tiket',
                'slug' => 'tickets-calendar',
                'route_name' => 'tickets.calendar',
                'icon' => 'calendar',
                'color' => 'sky',
                'group' => 'ticketing',
                'badge_text' => 'Due Date',
                'description' => 'Lihat estimasi selesai tiket per tanggal dan siapa yang mengerjakan.',
                'show_in_sidebar' => true,
                'show_in_launcher' => true,
                'sort_order' => 7,
                'roles' => $allActiveRoles,
            ],
            [
                'name' => 'Laporan Bulanan',
                'slug' => 'tickets-report',
                'route_name' => 'tickets.report',
                'icon' => 'document-check',
                'color' => 'sky',
                'group' => 'ticketing',
                'badge_text' => 'Kepala Role',
                'description' => 'Performa staff, progres, dan status pengerjaan pada bulan yang dipilih.',
                'show_in_sidebar' => true,
                'show_in_launcher' => false,
                'sort_order' => 8,
                'roles' => $allActiveRoles,
            ],
            [
                'name' => 'Pekerjaan Divisi',
                'slug' => 'tickets-workload',
                'route_name' => 'tickets.workload',
                'icon' => 'user-group',
                'color' => 'indigo',
                'group' => 'ticketing',
                'badge_text' => 'Sedang dikerjakan',
                'description' => 'Pekerjaan yang sedang dikerjakan IT dan setiap divisi.',
                'show_in_sidebar' => true,
                'show_in_launcher' => true,
                'sort_order' => 9,
                'roles' => $allActiveRoles,
            ],
            [
                'name' => 'Layanan & Role',
                'slug' => 'admin-services-roles',
                'route_name' => 'admin.services-roles',
                'icon' => 'adjustments-horizontal',
                'color' => 'teal',
                'group' => 'management',
                'badge_text' => 'Layanan & Role',
                'description' => 'Konfigurasi kategori layanan, alur approval persetujuan, dan manajemen role/unit kerja.',
                'show_in_sidebar' => true,
                'show_in_launcher' => true,
                'sort_order' => 5,
                'roles' => [$superuserRole->id],
            ],
            [
                'name' => 'Manajemen Menu Dinamis',
                'slug' => 'admin-menus',
                'route_name' => 'admin.menus',
                'icon' => 'squares-plus',
                'color' => 'rose',
                'group' => 'management',
                'badge_text' => 'Superuser',
                'description' => 'Atur menu SuperApp, ikon, urutan tampil, dan hak akses menu untuk setiap role.',
                'show_in_sidebar' => true,
                'show_in_launcher' => true,
                'sort_order' => 6,
                'roles' => [$superuserRole->id],
            ],
            [
                'name' => 'Manajemen Pengguna',
                'slug' => 'admin-users',
                'route_name' => 'admin.users',
                'icon' => 'users',
                'color' => 'fuchsia',
                'group' => 'management',
                'badge_text' => null,
                'description' => 'Kelola personel pengguna, status, profil dan reset password per unit role.',
                'show_in_sidebar' => true,
                'show_in_launcher' => true,
                'sort_order' => 7,
                'roles' => [$superuserRole->id],
            ],
            [
                'name' => 'Profil Saya',
                'slug' => 'profile-edit',
                'route_name' => 'profile.edit',
                'icon' => 'user',
                'color' => 'indigo',
                'group' => 'main',
                'badge_text' => 'Akun',
                'description' => 'Pengaturan profil, kontak, dan unggah foto profil pengguna.',
                'show_in_sidebar' => true,
                'show_in_launcher' => true,
                'sort_order' => 8,
                'roles' => $allActiveRoles,
            ],
        ];

        $createdMenus = [];
        foreach ($menusData as $item) {
            $roleIds = $item['roles'];
            unset($item['roles']);
            $menu = Menu::updateOrCreate(['slug' => $item['slug']], $item);
            $menu->roles()->sync($roleIds);
            $createdMenus[$menu->slug] = $menu;
        }

        // Sub-Sidebar di bawah Layanan & Role: 1. Kategori Layanan, 2. Manajemen Role
        if (isset($createdMenus['admin-services-roles'])) {
            $parentServiceRole = $createdMenus['admin-services-roles'];

            $subCategories = Menu::updateOrCreate(
                ['slug' => 'admin-categories'],
                [
                    'parent_id' => $parentServiceRole->id,
                    'name' => 'Kategori Layanan',
                    'route_name' => 'admin.categories',
                    'icon' => 'squares-plus',
                    'color' => 'teal',
                    'group' => 'management',
                    'badge_text' => null,
                    'description' => 'Daftar kategori layanan, alur approval persetujuan, dan template dokumen.',
                    'show_in_sidebar' => true,
                    'show_in_launcher' => false,
                    'sort_order' => 1,
                ]
            );
            $subCategories->roles()->sync([$superuserRole->id]);

            $subRoles = Menu::updateOrCreate(
                ['slug' => 'admin-roles'],
                [
                    'parent_id' => $parentServiceRole->id,
                    'name' => 'Manajemen Divisi',
                    'route_name' => 'admin.roles',
                    'icon' => 'users',
                    'color' => 'indigo',
                    'group' => 'management',
                    'badge_text' => null,
                    'description' => 'Susunan divisi: Kepala, SPV, lalu staff. Puncak divisi dipakai layanan.',
                    'show_in_sidebar' => true,
                    'show_in_launcher' => false,
                    'sort_order' => 2,
                ]
            );
            $subRoles->roles()->sync([$superuserRole->id]);
        }

        // Sub-Sidebar di bawah Manajemen Pengguna dihasilkan secara dinamis berdasarkan role unit yang aktif


        // 4. Buat Sample Dokumen PDF untuk Template Kategori Layanan & Lampiran Tiket
        $samplePdfContent = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Count 1/Kids[3 0 R]>>endobj\n3 0 obj<</Type/Page/MediaBox[0 0 595 842]/Parent 2 0 R/Resources<</Font<</F1 4 0 R>>>>/Contents 5 0 R>>endobj\n4 0 obj<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>endobj\n5 0 obj<</Length 152>>stream\nBT /F1 16 Tf 50 760 Td (TEMPLATE DOKUMEN LAYANAN KAMPUS USH) Tj /F1 12 Tf 0 -30 Td (Silakan lengkapi formulir ini dan unggah saat membuat tiket) Tj ET\nendstream\nendobj\nxref\n0 6\n0000000000 65535 f \n0000000009 00000 n \n0000000052 00000 n \n0000000101 00000 n \n0000000207 00000 n \n0000000269 00000 n \ntrailer<</Size 6/Root 1 0 R>>\nstartxref\n470\n%%EOF";
        \Illuminate\Support\Facades\Storage::disk('public')->put('category-templates/template_pengadaan_dana_ush.pdf', $samplePdfContent);
        \Illuminate\Support\Facades\Storage::disk('public')->put('category-templates/template_form_skripsi_ush.pdf', $samplePdfContent);
        \Illuminate\Support\Facades\Storage::disk('public')->put('attachments/proposal_skripsi_siti.pdf', $samplePdfContent);
        \Illuminate\Support\Facades\Storage::disk('public')->put('attachments/surat_dispensasi_gemastik.pdf', $samplePdfContent);

        // 5. Buat Kategori Layanan dengan Susunan Hirarki Role & Template Dokumen
        TicketCategory::create([
            'name' => 'Pengadaan Dana Kegiatan',
            'slug' => 'pengadaan-dana-kegiatan',
            'icon' => 'ticket',
            'color' => 'teal',
            'description' => 'Pengajuan pencairan anggaran & pengadaan dana kegiatan mahasiswa melalui verifikasi Akademik dan pengesahan Yayasan.',
            'workflow_roles' => [$mahasiswaRole->id, $akademikRole->id, $yayasanRole->id],
            'initial_target_role_id' => $akademikRole->id,
            'workflow_type' => 'tiered_approval',
            'max_approval_role_id' => $yayasanRole->id,
            'template_path' => 'category-templates/template_pengadaan_dana_ush.pdf',
            'template_name' => 'Template_Pengadaan_Dana_Kegiatan_USH.pdf',
            'is_attachment_required' => true,
            'is_active' => true,
        ]);

        $catKrs = TicketCategory::create([
            'name' => 'Konsultasi KRS & Perwalian Akademik',
            'slug' => 'konsultasi-krs-perwalian',
            'icon' => 'academic-cap',
            'color' => 'indigo',
            'description' => 'Pengajuan perubahan KRS, tambah/batal mata kuliah, atau konsultasi akademik ke Dosen Wali (bisa dieskalasi ke Kaprodi bila kuota kelas penuh).',
            'workflow_roles' => [$mahasiswaRole->id, $dosenRole->id, $kaprodiRole->id],
            'initial_target_role_id' => $dosenRole->id,
            'workflow_type' => 'escalation',
            'max_approval_role_id' => $kaprodiRole->id,
            'is_attachment_required' => false,
            'is_active' => true,
        ]);

        $catSkripsi = TicketCategory::create([
            'name' => 'Pengajuan Judul Skripsi / Tugas Akhir',
            'slug' => 'pengajuan-judul-skripsi',
            'icon' => 'document-check',
            'color' => 'emerald',
            'description' => 'Wajib melalui Approval Berjenjang: disetujui oleh Dosen Pembimbing terlebih dahulu, lalu otomatis naik ke Kaprodi untuk pengesahan.',
            'workflow_roles' => [$mahasiswaRole->id, $dosenRole->id, $kaprodiRole->id],
            'initial_target_role_id' => $dosenRole->id,
            'workflow_type' => 'tiered_approval',
            'max_approval_role_id' => $kaprodiRole->id,
            'template_path' => 'category-templates/template_form_skripsi_ush.pdf',
            'template_name' => 'Template_Form_Pengajuan_Judul_Skripsi.pdf',
            'is_attachment_required' => true,
            'is_active' => true,
        ]);

        $catDispensasi = TicketCategory::create([
            'name' => 'Dispensasi & Pendanaan Lomba Nasional',
            'slug' => 'dispensasi-lomba-nasional',
            'icon' => 'trophy',
            'color' => 'amber',
            'description' => 'Approval Berjenjang 3 Tingkat mulai dari Dosen → Kaprodi → hingga pengesahan akhir oleh Dekan.',
            'workflow_roles' => [$mahasiswaRole->id, $dosenRole->id, $kaprodiRole->id, $dekanRole->id],
            'initial_target_role_id' => $dosenRole->id,
            'workflow_type' => 'tiered_approval',
            'max_approval_role_id' => $dekanRole->id,
            'is_attachment_required' => false,
            'is_active' => true,
        ]);

        $catNilai = TicketCategory::create([
            'name' => 'Klarifikasi Nilai & Administrasi Perkuliahan',
            'slug' => 'klarifikasi-nilai-perkuliahan',
            'icon' => 'clipboard-document-list',
            'color' => 'rose',
            'description' => 'Tiket masuk ke Dosen pengampu/wali, dapat diselesaikan langsung atau dieskalasi ke Kaprodi.',
            'workflow_roles' => [$mahasiswaRole->id, $dosenRole->id, $kaprodiRole->id],
            'initial_target_role_id' => $dosenRole->id,
            'workflow_type' => 'escalation',
            'max_approval_role_id' => $kaprodiRole->id,
            'is_attachment_required' => false,
            'is_active' => true,
        ]);

        // Kategori Layanan Spesifik Divisi IT (Disetting Superadmin -> Masuk ke Supervisor IT -> Set Prioritas & Poin % -> Deliver ke Staff IT)
        $sampleItImageSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="720" height="360" viewBox="0 0 720 360"><rect width="720" height="360" rx="16" fill="#0f172a"/><rect x="24" y="24" width="672" height="44" rx="8" fill="#1e293b"/><circle cx="48" cy="46" r="7" fill="#ef4444"/><circle cx="70" cy="46" r="7" fill="#f59e0b"/><circle cx="92" cy="46" r="7" fill="#10b981"/><text x="120" y="51" fill="#94a3b8" font-family="monospace" font-size="13">MONITORING INFRASTRUKTUR DIVISI IT KAMPUS USH</text><rect x="24" y="88" width="672" height="248" rx="10" fill="#111827" stroke="#334155"/><text x="48" y="130" fill="#f87171" font-family="monospace" font-size="16" font-weight="bold">[ALERT] TANGKAPAN LAYAR BUKTI GANGGUAN IT</text><text x="48" y="165" fill="#e2e8f0" font-family="monospace" font-size="13">• Lokasi   : Gedung Laboratorium Terpadu / Ruang Server USH</text><text x="48" y="192" fill="#e2e8f0" font-family="monospace" font-size="13">• Status   : Request Masuk ke Supervisor IT untuk Delivery ke Staff</text><text x="48" y="219" fill="#38bdf8" font-family="monospace" font-size="13">• Lampiran : Foto / Screenshot Bukti Kendala Lapangan</text><rect x="48" y="250" width="624" height="54" rx="8" fill="#1e293b"/><text x="68" y="282" fill="#fbbf24" font-family="monospace" font-size="13">Menunggu pengecekan &amp; penyelesaian poin pekerjaan oleh Staff IT</text></svg>';
        \Illuminate\Support\Facades\Storage::disk('public')->put('attachments/bukti_gangguan_jaringan_lt3.svg', $sampleItImageSvg);
        \Illuminate\Support\Facades\Storage::disk('public')->put('attachments/bukti_error_pc_lab_b204.svg', $sampleItImageSvg);
        \Illuminate\Support\Facades\Storage::disk('public')->put('attachments/bukti_error_siakad_502.svg', $sampleItImageSvg);

        $catItJaringan = TicketCategory::create([
            'name' => 'IT — Gangguan Jaringan, Wi-Fi & Server',
            'slug' => 'it-gangguan-jaringan-wifi-server',
            'icon' => 'adjustments-horizontal',
            'color' => 'sky',
            'description' => 'Kategori spesifik Divisi IT untuk penanganan gangguan internet, switch, access point Wi-Fi, dan server. Masuk ke Supervisor IT untuk penentuan prioritas, rincian poin pekerjaan (%), dan delivery ke Staff IT.',
            'workflow_roles' => [$mahasiswaRole->id, $supervisorItRole->id],
            'initial_target_role_id' => $supervisorItRole->id,
            'workflow_type' => 'escalation',
            'max_approval_role_id' => $supervisorItRole->id,
            'is_attachment_required' => false,
            'is_active' => true,
        ]);

        $catItHardware = TicketCategory::create([
            'name' => 'IT — Perbaikan Perangkat Keras (PC, Laptop & Proyektor)',
            'slug' => 'it-perbaikan-perangkat-keras',
            'icon' => 'shield-check',
            'color' => 'violet',
            'description' => 'Kategori spesifik Divisi IT untuk perbaikan PC laboratorium, proyektor ruang kuliah, printer, dan perangkat keras. Masuk ke Supervisor IT untuk dideliver ke Staff Hardware.',
            'workflow_roles' => [$mahasiswaRole->id, $supervisorItRole->id],
            'initial_target_role_id' => $supervisorItRole->id,
            'workflow_type' => 'escalation',
            'max_approval_role_id' => $supervisorItRole->id,
            'is_attachment_required' => false,
            'is_active' => true,
        ]);

        $catItAplikasi = TicketCategory::create([
            'name' => 'IT — Kendala Sistem Informasi & Aplikasi SIAKAD',
            'slug' => 'it-kendala-sistem-aplikasi-siakad',
            'icon' => 'ticket',
            'color' => 'emerald',
            'description' => 'Kategori spesifik Divisi IT untuk penanganan bug aplikasi, akun portal akademik, dan database SIAKAD. Masuk ke Supervisor IT untuk dideliver ke Staff Aplikasi.',
            'workflow_roles' => [$mahasiswaRole->id, $supervisorItRole->id],
            'initial_target_role_id' => $supervisorItRole->id,
            'workflow_type' => 'escalation',
            'max_approval_role_id' => $supervisorItRole->id,
            'is_attachment_required' => false,
            'is_active' => true,
        ]);

        // Tiket 1: Baru dibuat oleh Mahasiswa Andi -> sedang di Inbox Dosen (Prioritas belum diset)
        $t1 = Ticket::create([
            'ticket_number' => 'TKT-202610-0001',
            'title' => 'Permohonan Buka Kelas Tambahan Mata Kuliah Cloud Computing',
            'description' => 'Selamat pagi Pak Budi, saya Andi Pratama (NIM 2310511042). Saat pengisian KRS, kuota kelas Cloud Computing sudah penuh padahal mata kuliah ini prasyarat semester depan. Mohon arahan dan bantuannya Pak.',
            'category_id' => $catKrs->id,
            'priority' => null,
            'workflow_type' => 'escalation',
            'creator_id' => $mahasiswa1->id,
            'creator_role_id' => $mahasiswaRole->id,
            'current_role_id' => $dosenRole->id,
            'assigned_user_id' => $dosen->id,
            'status' => 'open',
            'approval_step' => 1,
        ]);

        TicketLog::create([
            'ticket_id' => $t1->id,
            'user_id' => $mahasiswa1->id,
            'from_role_id' => $mahasiswaRole->id,
            'to_role_id' => $dosenRole->id,
            'action' => 'created',
            'note' => 'Tiket dibuat oleh Mahasiswa dan otomatis diarahkan ke role Dosen sesuai hirarki.',
        ]);

        // Tiket 2: Pengajuan Judul Skripsi oleh Siti -> Sudah di-approve Dosen & Kaprodi (Selesai / Approved) + Ada Dokumen PDF!
        $t2 = Ticket::create([
            'ticket_number' => 'TKT-202610-0002',
            'title' => 'Pengajuan Topik Skripsi: Sistem Deteksi Anomali Jaringan Berbasis Deep Learning',
            'description' => 'Yth. Bapak Dosen Pembimbing dan Ibu Kaprodi, berikut saya ajukan proposal ringkas topik tugas akhir mengenai deteksi anomali trafik jaringan kampus menggunakan arsitektur Transformer.',
            'attachment_path' => 'attachments/proposal_skripsi_siti.pdf',
            'attachment_name' => 'Proposal_Skripsi_Siti_Nabila.pdf',
            'category_id' => $catSkripsi->id,
            'priority' => 'medium',
            'workflow_type' => 'tiered_approval',
            'creator_id' => $mahasiswa2->id,
            'creator_role_id' => $mahasiswaRole->id,
            'current_role_id' => $kaprodiRole->id,
            'assigned_user_id' => $kaprodi->id,
            'status' => 'resolved',
            'approval_step' => 2,
            'resolved_at' => now(),
        ]);

        TicketLog::create([
            'ticket_id' => $t2->id,
            'user_id' => $mahasiswa2->id,
            'from_role_id' => $mahasiswaRole->id,
            'to_role_id' => $dosenRole->id,
            'action' => 'created',
            'note' => 'Tiket pengajuan judul skripsi dibuat oleh Mahasiswa. Lampiran: Proposal_Skripsi_Siti_Nabila.pdf',
        ]);

        TicketLog::create([
            'ticket_id' => $t2->id,
            'user_id' => $dosen->id,
            'from_role_id' => $dosenRole->id,
            'to_role_id' => $kaprodiRole->id,
            'action' => 'approved_step',
            'note' => 'Topik sangat relevan dengan roadmap riset lab. Disetujui di tingkat Dosen dan diteruskan ke Kaprodi.',
        ]);

        TicketLog::create([
            'ticket_id' => $t2->id,
            'user_id' => $kaprodi->id,
            'from_role_id' => $kaprodiRole->id,
            'to_role_id' => $kaprodiRole->id,
            'action' => 'final_approved',
            'note' => 'Judul skripsi disetujui oleh Kaprodi. Silakan lanjutkan penyusunan Bab 1-3.',
        ]);

        // Tiket 3: Tiket Mahasiswa Andi yang juga berada di Inbox Dosen (sedang diproses Dosen)
        $t3 = Ticket::create([
            'ticket_number' => 'TKT-202610-0003',
            'title' => 'Perbaikan Nilai Praktikum Basis Data Lanjut Semester Genap',
            'description' => 'Halo Pak Budi, nilai modul ke-5 praktikum Basis Data saya belum terinput di portal akademik padahal sudah asistensi. Mohon bantuannya untuk sinkronisasi nilai.',
            'category_id' => $catNilai->id,
            'priority' => 'urgent',
            'workflow_type' => 'escalation',
            'creator_id' => $mahasiswa1->id,
            'creator_role_id' => $mahasiswaRole->id,
            'current_role_id' => $dosenRole->id,
            'assigned_user_id' => $dosen->id,
            'status' => 'in_progress',
            'approval_step' => 1,
        ]);

        TicketLog::create([
            'ticket_id' => $t3->id,
            'user_id' => $mahasiswa1->id,
            'from_role_id' => $mahasiswaRole->id,
            'to_role_id' => $dosenRole->id,
            'action' => 'created',
            'note' => 'Tiket dibuat oleh Mahasiswa dan masuk ke dashboard Dosen.',
        ]);

        TicketLog::create([
            'ticket_id' => $t3->id,
            'user_id' => $dosen->id,
            'from_role_id' => $dosenRole->id,
            'to_role_id' => $dosenRole->id,
            'action' => 'in_progress',
            'note' => 'Sedang saya cek rekap nilai dari asisten laboratorium, mohon ditunggu.',
        ]);

        // Tiket 4: Dispensasi Lomba Andi yang sudah di-approve sampai Dekan + Ada Dokumen PDF!
        $t4 = Ticket::create([
            'ticket_number' => 'TKT-202610-0004',
            'title' => 'Permohonan Dispensasi Kuliah & Surat Tugas Finalis Gemastik Nasional',
            'description' => 'Tim kami lolos ke babak final Gemastik selama 4 hari minggu depan. Mohon persetujuan dispensasi kehadiran perkuliahan dan surat tugas resmi fakultas.',
            'attachment_path' => 'attachments/surat_dispensasi_gemastik.pdf',
            'attachment_name' => 'Surat_Undangan_Finalis_Gemastik.pdf',
            'category_id' => $catDispensasi->id,
            'priority' => 'high',
            'workflow_type' => 'tiered_approval',
            'creator_id' => $mahasiswa1->id,
            'creator_role_id' => $mahasiswaRole->id,
            'current_role_id' => $dekanRole->id,
            'assigned_user_id' => $dekan->id,
            'status' => 'resolved',
            'approval_step' => 3,
            'resolved_at' => now(),
        ]);

        TicketLog::create([
            'ticket_id' => $t4->id,
            'user_id' => $mahasiswa1->id,
            'from_role_id' => $mahasiswaRole->id,
            'to_role_id' => $dosenRole->id,
            'action' => 'created',
            'note' => 'Pengajuan dispensasi dibuat oleh Mahasiswa ke Dosen Wali. Lampiran: Surat_Undangan_Finalis_Gemastik.pdf',
        ]);

        TicketLog::create([
            'ticket_id' => $t4->id,
            'user_id' => $dosen->id,
            'from_role_id' => $dosenRole->id,
            'to_role_id' => $kaprodiRole->id,
            'action' => 'approved_step',
            'note' => 'Disetujui Dosen Wali. Diteruskan sesuai hirarki ke Kaprodi.',
        ]);

        TicketLog::create([
            'ticket_id' => $t4->id,
            'user_id' => $kaprodi->id,
            'from_role_id' => $kaprodiRole->id,
            'to_role_id' => $dekanRole->id,
            'action' => 'approved_step',
            'note' => 'Prodi mendukung penuh keikutsertaan tim. Diteruskan ke Dekan untuk surat tugas fakultas.',
        ]);

        TicketLog::create([
            'ticket_id' => $t4->id,
            'user_id' => $dekan->id,
            'from_role_id' => $dekanRole->id,
            'to_role_id' => $dekanRole->id,
            'action' => 'final_approved',
            'note' => 'Disetujui oleh Dekan. Surat tugas dan dispensasi perkuliahan resmi diterbitkan.',
        ]);

        // Tiket 5 (Kasus Divisi IT #1 — BARU MASUK DI SUPERVISOR IT):
        // Request masuk dari Kategori IT spesifik + Deskripsi + Gambar Bukti -> Masuk ke Inbox Supervisor IT, siap ditentukan Priority, Poin Pekerjaan (%), dan Delivery ke Staff!
        $t5 = Ticket::create([
            'ticket_number' => 'TKT-202610-0005',
            'title' => 'Error 502 Bad Gateway Saat Sinkronisasi KRS di Portal SIAKAD',
            'description' => 'Selamat pagi tim Divisi IT, sejak pukul 09.15 WIB halaman sinkronisasi KRS di portal SIAKAD menampilkan pesan Error 502 Bad Gateway seperti pada gambar tangkapan layar terlampir. Mohon bantuannya untuk pengecekan service backend dan database.',
            'attachment_path' => 'attachments/bukti_error_siakad_502.svg',
            'attachment_name' => 'Screenshot_Error_502_SIAKAD.svg',
            'category_id' => $catItAplikasi->id,
            'priority' => 'urgent',
            'priority_rank' => 1,
            'workflow_type' => 'escalation',
            'creator_id' => $adminItLead->id,
            'creator_role_id' => $adminItRole->id,
            'current_role_id' => $supervisorItRole->id,
            'assigned_user_id' => $supervisorItUser->id,
            'status' => 'open',
            'approval_step' => 1,
        ]);

        TicketLog::create([
            'ticket_id' => $t5->id,
            'user_id' => $adminItLead->id,
            'from_role_id' => $adminItRole->id,
            'to_role_id' => $supervisorItRole->id,
            'action' => 'created',
            'note' => 'Request layanan IT dibuat dengan lampiran gambar bukti kendala dan masuk ke Inbox Supervisor IT untuk penentuan prioritas, input poin pekerjaan (%), serta delivery ke Staff IT.',
        ]);

        // Tiket 6 (Kasus Divisi IT #2 — SUDAH DIDELIVER SUPERVISOR IT KE STAFF JARINGAN DENGAN POIN % -> PROGRES 70%):
        // Supervisor IT menetapkan 3 poin pekerjaan (30% + 40% + 30% = 100%) dan mendeliver ke Fajar Nugroho (Staff Jaringan).
        // Fajar sudah mencentang 2 poin (30% + 40%), sehingga progres otomatis tercatat 70%!
        $t6 = Ticket::create([
            'ticket_number' => 'TKT-202610-0006',
            'title' => 'Gangguan Koneksi Switch & Access Point Wi-Fi Laboratorium Terpadu Lt. 3',
            'description' => 'Koneksi internet dan jaringan lokal di Laboratorium Terpadu Lantai 3 terputus sejak pukul 08.00 WIB sehingga PC praktikum tidak dapat mengakses server lokal. Foto indikator switch terlampir.',
            'attachment_path' => 'attachments/bukti_gangguan_jaringan_lt3.svg',
            'attachment_name' => 'Foto_Indikator_Switch_Lt3.svg',
            'category_id' => $catItJaringan->id,
            'priority' => 'urgent',
            'priority_rank' => 1,
            'assessed_priority_rank' => 1,
            'workflow_type' => 'escalation',
            'creator_id' => $mahasiswa1->id,
            'creator_role_id' => $mahasiswaRole->id,
            'current_role_id' => $supervisorItRole->id,
            'assigned_user_id' => $adminItJaringan->id,
            'status' => 'in_progress',
            'work_status' => 'in_progress',
            'progress_percent' => 70,
            'progress_note' => 'Switch distribusi lantai 3 dan konfigurasi VLAN sudah selesai (70%). Sedang lanjut uji ping di 30 PC klien (30%).',
            'approval_step' => 1,
        ]);

        TicketProblem::create([
            'ticket_id' => $t6->id,
            'title' => 'Pengecekan jalur kabel Fiber Optic & port uplink switch lantai 3',
            'weight_percent' => 30,
            'is_done' => true,
            'done_by' => $adminItJaringan->id,
            'done_at' => now()->subMinutes(40),
            'sort_order' => 1,
        ]);

        TicketProblem::create([
            'ticket_id' => $t6->id,
            'title' => 'Konfigurasi ulang VLAN laboratorium pada Mikrotik Core Router',
            'weight_percent' => 40,
            'is_done' => true,
            'done_by' => $adminItJaringan->id,
            'done_at' => now()->subMinutes(20),
            'sort_order' => 2,
        ]);

        TicketProblem::create([
            'ticket_id' => $t6->id,
            'title' => 'Pengujian stabilitas ping & bandwidth di 30 PC klien laboratorium',
            'weight_percent' => 30,
            'is_done' => false,
            'sort_order' => 3,
        ]);

        TicketLog::create([
            'ticket_id' => $t6->id,
            'user_id' => $mahasiswa1->id,
            'from_role_id' => $mahasiswaRole->id,
            'to_role_id' => $supervisorItRole->id,
            'action' => 'created',
            'note' => 'Request gangguan jaringan masuk ke Supervisor IT beserta foto bukti indikator switch.',
        ]);

        TicketLog::create([
            'ticket_id' => $t6->id,
            'user_id' => $supervisorItUser->id,
            'from_role_id' => $supervisorItRole->id,
            'to_role_id' => $supervisorItRole->id,
            'action' => 'problems_set',
            'note' => 'Supervisor menetapkan 3 poin pekerjaan untuk staff: Pengecekan jalur kabel Fiber Optic & port uplink switch lantai 3 (30%); Konfigurasi ulang VLAN laboratorium pada Mikrotik Core Router (40%); Pengujian stabilitas ping & bandwidth di 30 PC klien laboratorium (30%).',
        ]);

        TicketLog::create([
            'ticket_id' => $t6->id,
            'user_id' => $supervisorItUser->id,
            'from_role_id' => $supervisorItRole->id,
            'to_role_id' => $supervisorItRole->id,
            'action' => 'priority_set',
            'note' => 'Prioritas 1 (Mendesak) ditetapkan oleh Supervisor IT dan tugas di-deliver ke Fajar Nugroho, S.T. (keahlian: Jaringan & Server).',
        ]);

        TicketLog::create([
            'ticket_id' => $t6->id,
            'user_id' => $adminItJaringan->id,
            'from_role_id' => $staffRole->id,
            'to_role_id' => $supervisorItRole->id,
            'action' => 'problem_done',
            'note' => 'Masalah selesai: Konfigurasi ulang VLAN laboratorium pada Mikrotik Core Router (40%). Total progres pengerjaan: 70%.',
        ]);

        // Tiket 7 (Kasus Divisi IT #3 — SUDAH DIDELIVER SUPERVISOR IT KE STAFF HARDWARE DENGAN POIN % -> PROGRES 30%):
        $t7 = Ticket::create([
            'ticket_number' => 'TKT-202610-0007',
            'title' => 'Perbaikan 4 Unit PC Praktikum & Proyektor Ruang Kelas B-204',
            'description' => 'Mohon bantuan teknisi hardware untuk mengecek 4 unit PC yang mati total serta proyektor ruang B-204 yang tampilan warnanya bergaris seperti pada foto terlampir.',
            'attachment_path' => 'attachments/bukti_error_pc_lab_b204.svg',
            'attachment_name' => 'Foto_Kondisi_PC_dan_Proyektor_B204.svg',
            'category_id' => $catItHardware->id,
            'priority' => 'high',
            'priority_rank' => 2,
            'assessed_priority_rank' => 2,
            'workflow_type' => 'escalation',
            'creator_id' => $dosen->id,
            'creator_role_id' => $dosenRole->id,
            'current_role_id' => $supervisorItRole->id,
            'assigned_user_id' => $adminItHardware->id,
            'status' => 'in_progress',
            'work_status' => 'in_progress',
            'progress_percent' => 30,
            'progress_note' => 'Konektor HDMI & kalibrasi proyektor sudah selesai (30%). Sedang lanjut penggantian PSU pada PC praktikum.',
            'approval_step' => 1,
        ]);

        TicketProblem::create([
            'ticket_id' => $t7->id,
            'title' => 'Penggantian konektor HDMI & kalibrasi fokus proyektor ruang B-204',
            'weight_percent' => 30,
            'is_done' => true,
            'done_by' => $adminItHardware->id,
            'done_at' => now()->subHour(),
            'sort_order' => 1,
        ]);

        TicketProblem::create([
            'ticket_id' => $t7->id,
            'title' => 'Penggantian unit Power Supply (PSU) 500W pada PC-08 dan PC-12',
            'weight_percent' => 50,
            'is_done' => false,
            'sort_order' => 2,
        ]);

        TicketProblem::create([
            'ticket_id' => $t7->id,
            'title' => 'Uji nyala & pengecekan suhu hardware seluruh 4 PC praktikum',
            'weight_percent' => 20,
            'is_done' => false,
            'sort_order' => 3,
        ]);

        TicketLog::create([
            'ticket_id' => $t7->id,
            'user_id' => $dosen->id,
            'from_role_id' => $dosenRole->id,
            'to_role_id' => $supervisorItRole->id,
            'action' => 'created',
            'note' => 'Request perbaikan perangkat keras diajukan ke Supervisor IT.',
        ]);

        TicketLog::create([
            'ticket_id' => $t7->id,
            'user_id' => $supervisorItUser->id,
            'from_role_id' => $supervisorItRole->id,
            'to_role_id' => $supervisorItRole->id,
            'action' => 'priority_set',
            'note' => 'Supervisor IT menetapkan 3 poin pekerjaan (30%, 50%, 20%) dan men-deliver tugas ke Bagas Setiawan, A.Md.Kom. (keahlian: Perangkat keras).',
        ]);
    }
}
