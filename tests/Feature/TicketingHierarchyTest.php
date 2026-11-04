<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\DivisionMember;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TicketingHierarchyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_superuser_can_create_new_role_and_assign_hierarchy_to_dosen_and_kaprodi(): void
    {
        $superuser = User::where('email', 'superuser@kampus.ac.id')->firstOrFail();
        $dosenRole = Role::where('slug', 'dosen')->firstOrFail();
        $kaprodiRole = Role::where('slug', 'kaprodi')->firstOrFail();

        $this->assertEquals($kaprodiRole->id, $dosenRole->parent_id);

        $response = $this->actingAs($superuser)->post(route('admin.hierarchy.store'), [
            'name' => 'Asisten Dosen',
            'parent_id' => $dosenRole->id,
            'badge_color' => 'indigo',
            'description' => 'Membantu praktikum dan meneruskan tiket ke Dosen.',
            'can_create_ticket' => 1,
            'can_receive_ticket' => 1,
        ]);

        $response->assertRedirect();
        $newRole = Role::where('name', 'Asisten Dosen')->first();
        $this->assertNotNull($newRole);
        $this->assertEquals($dosenRole->id, $newRole->parent_id);
        $this->assertEquals(['Asisten Dosen', 'Dosen', 'Kaprodi', 'Dekan'], $newRole->getUpwardChainNames());
    }

    public function test_mahasiswa_creates_ticket_with_optional_document_and_dosen_sets_priority_and_escalates(): void
    {
        Storage::fake('public');

        $mahasiswa = User::where('email', 'mahasiswa@kampus.ac.id')->firstOrFail();
        $dosen = User::where('email', 'dosen@kampus.ac.id')->firstOrFail();
        $kaprodi = User::where('email', 'kaprodi@kampus.ac.id')->firstOrFail();
        $category = TicketCategory::where('workflow_type', 'escalation')->firstOrFail();

        $file = UploadedFile::fake()->create('surat_permohonan.pdf', 200, 'application/pdf');

        // 1. Mahasiswa membuat tiket (tanpa prioritas, dengan dokumen opsional)
        $this->actingAs($mahasiswa)->post(route('tickets.store'), [
            'title' => 'Pengajuan Penambahan Kuota KRS',
            'category_id' => $category->id,
            'description' => 'Mohon bantuan pembukaan kuota KRS untuk mata kuliah pilihan.',
            'priority_rank' => 2,
            'attachment' => $file,
        ])->assertRedirect();

        $ticket = Ticket::where('title', 'Pengajuan Penambahan Kuota KRS')->firstOrFail();
        $this->assertSame(2, (int) $ticket->priority_rank);
        $this->assertSame('high', $ticket->priority);
        $this->assertEquals('surat_permohonan.pdf', $ticket->attachment_name);
        $this->assertNotNull($ticket->attachment_path);
        $this->assertEquals($dosen->role_id, $ticket->current_role_id);

        // Pastikan dokumen dapat di-preview secara inline maupun diunduh
        $this->actingAs($dosen)->get(route('tickets.preview', $ticket))->assertOk();
        $this->actingAs($dosen)->get(route('tickets.attachment', $ticket))->assertOk();

        // 2. Dosen mencari & memfilter tiket di Tiket Masuk berdasarkan kategori, lalu mengatur Prioritas
        $this->actingAs($dosen)->get(route('tickets.inbox', [
            'search' => 'Kuota KRS',
            'category_id' => $category->id,
        ]))->assertOk()->assertSee('Pengajuan Penambahan Kuota KRS');

        $this->actingAs($dosen)->post(route('tickets.priority', $ticket), [
            'priority' => 'high',
        ])->assertRedirect();

        $ticket->refresh();
        $this->assertEquals('high', $ticket->priority);

        // 3. Dosen melakukan eskalasi ke Kaprodi
        $this->actingAs($dosen)->post(route('tickets.action', $ticket), [
            'action_type' => 'escalate',
            'priority' => 'urgent',
            'note' => 'Mohon kebijakan Ibu Kaprodi untuk penambahan kuota kelas.',
        ])->assertRedirect();

        $ticket->refresh();
        $this->assertEquals($kaprodi->role_id, $ticket->current_role_id);
        $this->assertEquals('urgent', $ticket->priority);
        $this->assertEquals('escalated', $ticket->status);

        // Saat masih dieskalasi (sedang diproses), tiket tampil di menu "Tiket Saya" beserta tombol "+ Buat Tiket Baru"
        $this->actingAs($mahasiswa)->get(route('tickets.my'))
            ->assertOk()
            ->assertSee('+ Buat Tiket Baru')
            ->assertSee('Pengajuan Penambahan Kuota KRS');

        // Dan belum tampil di menu "Riwayat Tiket" (karena belum berstatus approve/resolved)
        $this->actingAs($mahasiswa)->get(route('tickets.history'))
            ->assertOk()
            ->assertDontSee('Pengajuan Penambahan Kuota KRS');

        // 4. Kaprodi menyelesaikan/menyetujui tiket, lalu Mahasiswa melihat di menu "Riwayat Tiket" (fokus yang berstatus Approve saja)
        $this->actingAs($kaprodi)->post(route('tickets.action', $ticket), [
            'action_type' => 'resolve',
            'note' => 'Disetujui penambahan kuota.',
        ])->assertRedirect();

        $this->actingAs($mahasiswa)->get(route('tickets.history'))
            ->assertOk()
            ->assertSee('Pengajuan Penambahan Kuota KRS')
            ->assertSee('Preview Dokumen');
    }

    public function test_all_main_pages_render_successfully_across_roles(): void
    {
        $users = User::all();
        foreach ($users as $user) {
            $this->actingAs($user)->get(route('dashboard'))->assertOk();
            $this->actingAs($user)->get(route('tickets.inbox'))->assertOk();
            $this->actingAs($user)->get(route('tickets.my'))->assertOk();
            $this->actingAs($user)->get(route('tickets.history'))->assertOk();
            $this->actingAs($user)->get(route('tickets.create'))->assertRedirect(route('tickets.my'));
        }

        $superuser = User::where('email', 'superuser@kampus.ac.id')->firstOrFail();
        $this->actingAs($superuser)->get(route('admin.hierarchy'))->assertRedirect(route('admin.categories'));
        $this->actingAs($superuser)->get(route('admin.menus'))->assertOk();
        $this->actingAs($superuser)->get(route('admin.categories'))->assertOk();
        $firstRole = Role::where('is_superuser', false)->whereHas('users')->first() ?? Role::where('is_superuser', false)->first();
        $this->actingAs($superuser)->get(route('admin.users'))->assertRedirect(route('admin.users.role', $firstRole->slug));
        $this->actingAs($superuser)->get(route('admin.users.role', $firstRole->slug))->assertOk();
    }

    public function test_role_based_user_management_with_profile_modal_and_password_reset(): void
    {
        $superuser = User::where('email', 'superuser@kampus.ac.id')->firstOrFail();
        $itRole = Role::where('slug', 'admin-it')->orWhere('slug', 'informatic-tecnology')->firstOrFail();

        // 1. Superuser membuka halaman user role IT
        $response = $this->actingAs($superuser)->get(route('admin.users.role', $itRole->slug));
        $response->assertOk()
            ->assertSee($itRole->name)
            ->assertSee('Tambah Pengguna')
            ->assertSee('Reset Password');

        // 2. Superuser menambahkan pengguna baru ke role IT
        $newEmail = 'teknisi.baru@kampus.ac.id';
        $this->actingAs($superuser)->post(route('admin.users.store'), [
            'name' => 'Teknisi Jaringan Baru',
            'email' => $newEmail,
            'password' => 'rahasia123',
            'role_id' => $itRole->id,
            'jabatan' => 'Teknisi Jaringan',
            'keahlian' => 'Mikrotik, Fiber Optic',
            'status_akademik' => 'Aktif',
            'is_role_head' => 0,
        ])->assertRedirect()->assertSessionHas('success');

        $newUser = User::where('email', $newEmail)->firstOrFail();
        $this->assertEquals($itRole->id, $newUser->role_id);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('rahasia123', $newUser->password));

        // 3. Superuser mereset password pengguna ke password kustom
        $this->actingAs($superuser)->post(route('admin.users.reset-password', $newUser), [
            'password' => 'passwordbaru2026',
        ])->assertRedirect()->assertSessionHas('success');

        $newUser->refresh();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('passwordbaru2026', $newUser->password));

        // 4. Superuser mereset password ke default ('password')
        $this->actingAs($superuser)->post(route('admin.users.reset-password', $newUser), [
            'password' => '',
        ])->assertRedirect()->assertSessionHas('success');

        $newUser->refresh();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('password', $newUser->password));

        // 5. Dynamic sidebar memuat role sebagai sub-menu di Manajemen Pengguna
        $dashboardResponse = $this->actingAs($superuser)->get(route('dashboard'));
        $dashboardResponse->assertOk()
            ->assertSee('/admin/users/role/' . $itRole->slug, false)
            ->assertSee($itRole->name);
    }

    public function test_superuser_creates_service_category_with_custom_hierarchy_and_template_document(): void
    {
        Storage::fake('public');

        $superuser = User::where('email', 'superuser@kampus.ac.id')->firstOrFail();
        $mahasiswa = User::where('email', 'mahasiswa@kampus.ac.id')->firstOrFail();
        $akademik = User::where('email', 'akademik@kampus.ac.id')->firstOrFail();
        $yayasan = User::where('email', 'yayasan@kampus.ac.id')->firstOrFail();

        $mahasiswaRole = Role::where('slug', 'mahasiswa')->firstOrFail();
        $akademikRole = Role::where('slug', 'akademik')->firstOrFail();
        $yayasanRole = Role::where('slug', 'yayasan')->firstOrFail();

        $templateFile = UploadedFile::fake()->create('Template_Pengadaan_Dana_Ormawa.pdf', 150, 'application/pdf');

        // 1. Superuser membuat Kategori Layanan "Pengadaan Dana Ormawa" dengan hirarki: Mahasiswa -> Akademik -> Yayasan + Template Dokumen
        $this->actingAs($superuser)->post(route('admin.categories.store'), [
            'name' => 'Pengadaan Dana Ormawa',
            'workflow_type' => 'tiered_approval',
            'description' => 'Pengajuan dana kegiatan organisasi mahasiswa melalui Akademik dan Yayasan.',
            'workflow_roles' => [$mahasiswaRole->id, $akademikRole->id, $yayasanRole->id],
            'template_file' => $templateFile,
            'is_attachment_required' => 1,
        ])->assertRedirect();

        $category = TicketCategory::where('name', 'Pengadaan Dana Ormawa')->firstOrFail();
        $this->assertTrue($category->hasTemplate());
        $this->assertEquals('Template_Pengadaan_Dana_Ormawa.pdf', $category->template_name);
        $this->assertTrue($category->is_attachment_required);
        $this->assertEquals(
            ['Mahasiswa', 'Akademik', 'Yayasan'],
            $category->getFullHierarchyRoles()->pluck('name')->all()
        );

        // 2. Mahasiswa dapat preview & mengunduh template dokumen layanan
        $this->actingAs($mahasiswa)->get(route('categories.template.preview', $category))->assertOk();
        $this->actingAs($mahasiswa)->get(route('categories.template.download', $category))->assertOk();

        // 3. Mahasiswa mengajukan tiket pada layanan "Pengadaan Dana Ormawa" dengan mengunggah dokumen yang sudah diisi
        $filledDoc = UploadedFile::fake()->create('Form_Pengadaan_Dana_Terisi.pdf', 220, 'application/pdf');

        $this->actingAs($mahasiswa)->post(route('tickets.store'), [
            'title' => 'Pengajuan Dana Seminar Nasional AI',
            'category_id' => $category->id,
            'description' => 'Mohon persetujuan pencairan dana kegiatan seminar nasional.',
            'priority_rank' => 1,
            'attachment' => $filledDoc,
        ])->assertRedirect();

        $ticket = Ticket::where('title', 'Pengajuan Dana Seminar Nasional AI')->firstOrFail();
        // Tahap pertama setelah Mahasiswa adalah Akademik
        $this->assertEquals($akademikRole->id, $ticket->current_role_id);

        // 4. Role Akademik menyetujui tahap 1 -> otomatis naik ke Role Yayasan
        $this->actingAs($akademik)->post(route('tickets.action', $ticket), [
            'action_type' => 'approve_step',
            'priority' => 'high',
            'note' => 'Proposal dan RAB telah diverifikasi oleh bagian Akademik, diteruskan ke Yayasan.',
        ])->assertRedirect();

        $ticket->refresh();
        $this->assertEquals($yayasanRole->id, $ticket->current_role_id);
        $this->assertEquals('waiting_next_approval', $ticket->status);

        // 5. Role Yayasan menyetujui tahap akhir -> status tiket menjadi resolved (Approve)
        $this->actingAs($yayasan)->post(route('tickets.action', $ticket), [
            'action_type' => 'approve_step',
            'note' => 'Pengadaan dana disetujui oleh Yayasan.',
        ])->assertRedirect();

        $ticket->refresh();
        $this->assertEquals('resolved', $ticket->status);
    }

    public function test_superuser_can_create_and_edit_flexible_menu_and_sub_sidebar(): void
    {
        $superuser = User::where('email', 'superuser@kampus.ac.id')->firstOrFail();
        $parentMenu = \App\Models\Menu::where('slug', 'admin-users')->firstOrFail();
        $superuserRole = Role::where('slug', 'superuser')->firstOrFail();

        $this->actingAs($superuser)->post(route('admin.menus.store'), [
            'name' => 'Direktori Tendik',
            'parent_id' => $parentMenu->id,
            'route_name' => '/admin/roles',
            'group' => 'management',
            'icon' => 'user-group',
            'sort_order' => 3,
            'show_in_sidebar' => 1,
            'is_active' => 1,
            'roles' => [$superuserRole->id],
        ])->assertRedirect();

        $subMenu = \App\Models\Menu::where('name', 'Direktori Tendik')->firstOrFail();
        $this->assertEquals($parentMenu->id, $subMenu->parent_id);
        $this->assertEquals('/admin/roles', $subMenu->resolveUrl());

        $this->actingAs($superuser)->put(route('admin.menus.update', $subMenu), [
            'name' => 'Direktori Staf & Pengajar',
            'parent_id' => $parentMenu->id,
            'route_name' => 'admin.roles',
            'group' => 'management',
            'icon' => 'users',
            'sort_order' => 4,
            'show_in_sidebar' => 1,
            'is_active' => 1,
            'roles' => [$superuserRole->id],
        ])->assertRedirect();

        $subMenu->refresh();
        $this->assertEquals('Direktori Staf & Pengajar', $subMenu->name);
        $this->assertEquals(4, $subMenu->sort_order);
    }

    public function test_admin_it_role_routes_priority_number_to_the_chosen_staff(): void
    {
        $superuser = User::where('email', 'superuser@kampus.ac.id')->firstOrFail();
        $mahasiswa = User::where('email', 'mahasiswa@kampus.ac.id')->firstOrFail();
        $category = TicketCategory::firstOrFail();

        $this->actingAs($superuser)->post(route('admin.hierarchy.store'), [
            'name' => 'Admin IT',
            'badge_color' => 'indigo',
            'description' => 'Menangani tiket perangkat dan jaringan.',
            'can_create_ticket' => 1,
            'can_receive_ticket' => 1,
            'from_role_modal' => 1,
            'head_index' => 0,
            'staff' => [
                ['name' => 'Kepala IT', 'email' => 'kepala.it@kampus.ac.id', 'keahlian' => 'Koordinasi'],
                ['name' => 'Staff Jaringan', 'email' => 'staff.jaringan@kampus.ac.id', 'keahlian' => 'Jaringan'],
                ['name' => 'Staff Hardware', 'email' => 'staff.hardware@kampus.ac.id', 'keahlian' => 'Perangkat keras'],
            ],
        ])->assertRedirect();

        $role = Role::where('name', 'Admin IT')->latest('id')->firstOrFail();
        $head = User::where('email', 'kepala.it@kampus.ac.id')->firstOrFail();
        $network = User::where('email', 'staff.jaringan@kampus.ac.id')->firstOrFail();
        $hardware = User::where('email', 'staff.hardware@kampus.ac.id')->firstOrFail();

        $this->assertEquals($head->id, $role->head_user_id);
        $this->assertEquals('Jaringan', $network->keahlian);
        $this->assertEquals('Perangkat keras', $hardware->keahlian);

        $this->actingAs($superuser)->get(route('admin.categories'))
            ->assertOk();

        $this->actingAs($superuser)->get(route('admin.roles'))
            ->assertOk()
            ->assertSee('Kepala IT')
            ->assertSee('Perangkat keras');

        $ticket = Ticket::create([
            'ticket_number' => 'TKT-TEST-9001',
            'title' => 'Laptop lab tidak menyala',
            'description' => 'Mohon dicek perangkat lab informatika.',
            'category_id' => $category->id,
            'workflow_type' => 'escalation',
            'creator_id' => $mahasiswa->id,
            'creator_role_id' => $mahasiswa->role_id,
            'current_role_id' => $role->id,
            'status' => 'open',
        ]);

        $this->actingAs($network)->get(route('tickets.inbox'))
            ->assertOk()
            ->assertDontSee('Laptop lab tidak menyala');

        $this->actingAs($head)->get(route('tickets.inbox'))
            ->assertOk()
            ->assertSee('Laptop lab tidak menyala')
            ->assertSee('Perangkat keras');

        $this->actingAs($head)->post(route('tickets.priority', $ticket), [
            'priority_rank' => 1,
        ])->assertRedirect()->assertSessionHas('error');

        $ticket->refresh();
        $this->assertNull($ticket->priority_rank);
        $this->assertNotEquals($hardware->id, $ticket->assigned_user_id);

        $this->travel(2)->minutes();
        $this->actingAs($head)->post(route('tickets.priority', $ticket), [
            'priority_rank' => 1,
            'assigned_user_id' => $hardware->id,
        ])->assertRedirect()->assertSessionHas('success');

        $ticket->refresh();
        $this->assertEquals(1, $ticket->priority_rank);
        $this->assertEquals($hardware->id, $ticket->assigned_user_id);
        $this->assertNotNull($ticket->delivered_at);
        $this->assertTrue($ticket->delivered_at->greaterThan($ticket->created_at));
        $this->assertSame('delivered', $ticket->work_status);

        $deliveredAt = $ticket->delivered_at->copy();
        $this->actingAs($hardware)->post(route('tickets.progress', $ticket), [
            'work_status' => 'in_progress',
            'progress_percent' => 10,
            'progress_note' => 'Mulai cek perangkat.',
        ])->assertRedirect();
        $ticket->refresh();
        $this->assertEquals($deliveredAt->format('Y-m-d H:i:s'), $ticket->delivered_at->format('Y-m-d H:i:s'));

        $this->actingAs($hardware)->get(route('tickets.inbox'))
            ->assertOk()
            ->assertSee('Laptop lab tidak menyala')
            ->assertSee('Request date')
            ->assertSee('Delivered date');

        $this->actingAs($network)->get(route('tickets.inbox'))
            ->assertOk()
            ->assertDontSee('Laptop lab tidak menyala');

        $this->actingAs($head)->get(route('tickets.show', $ticket))
            ->assertOk()
            ->assertSee('Request')
            ->assertSee('Delivered')
            ->assertSee('Arahkan ke staff')
            ->assertSee('Sesuai, proses dan bagikan ke staff')
            ->assertSee('Tidak sesuai, tolak')
            ->assertSee('Perangkat keras');

        $mismatch = Ticket::create([
            'ticket_number' => 'TKT-TEST-9002',
            'title' => 'Cek printer ruang dosen',
            'description' => 'Printer hanya macet, bukan gangguan mendesak.',
            'category_id' => $category->id,
            'workflow_type' => 'escalation',
            'creator_id' => $mahasiswa->id,
            'creator_role_id' => $mahasiswa->role_id,
            'current_role_id' => $role->id,
            'priority_rank' => 1,
            'priority' => 'urgent',
            'status' => 'open',
        ]);

        $this->actingAs($head)->post(route('tickets.action', $mismatch), [
            'action_type' => 'reject',
            'priority_rank' => 1,
            'note' => 'Isinya pengecekan printer, bukan level urgent.',
        ])->assertRedirect()->assertSessionHas('success');

        $mismatch->refresh();
        $this->assertSame('rejected', $mismatch->status);
        $this->assertSame(1, (int) $mismatch->priority_rank);
    }

    public function test_intermediate_role_only_approves_and_forwards_upward(): void
    {
        $kaprodi = User::where('email', 'kaprodi@kampus.ac.id')->firstOrFail();
        $akademikUser = User::where('email', 'akademik@kampus.ac.id')->firstOrFail();
        $akademik = Role::where('name', 'Akademik')->firstOrFail();
        $yayasan = Role::where('name', 'Yayasan')->firstOrFail();

        $category = TicketCategory::create([
            'name' => 'Layanan Lewat Persetujuan',
            'slug' => 'layanan-lewat-persetujuan',
            'workflow_type' => 'escalation',
            'workflow_roles' => [$akademik->id, $yayasan->id],
            'initial_target_role_id' => $akademik->id,
            'is_active' => true,
            'is_attachment_required' => false,
        ]);

        $this->actingAs($kaprodi)->post(route('tickets.store'), [
            'title' => 'Permintaan dari Kaprodi',
            'category_id' => $category->id,
            'description' => 'Akademik hanya menyetujui, lalu tiket naik.',
            'priority_rank' => 2,
        ])->assertRedirect();

        $ticket = Ticket::where('title', 'Permintaan dari Kaprodi')->firstOrFail();
        $this->assertSame($akademik->id, $ticket->current_role_id);

        $this->actingAs($akademikUser)
            ->get(route('tickets.show', $ticket))
            ->assertOk()
            ->assertSee('Setuju, lanjut ke '.$yayasan->name)
            ->assertDontSee('Tandai Sedang Diproses')
            ->assertDontSee('Selesaikan Tiket')
            ->assertDontSee('Masalah &amp; Progres');

        $this->actingAs($akademikUser)->post(route('tickets.action', $ticket), [
            'action_type' => 'approve_step',
        ])->assertRedirect()->assertSessionHas('success');

        $ticket->refresh();
        $this->assertSame($yayasan->id, $ticket->current_role_id);
        $this->assertSame('waiting_next_approval', $ticket->status);
    }

    public function test_head_defines_ticket_problems_and_monitors_staff_progress_percent(): void
    {
        $superuser = User::where('email', 'superuser@kampus.ac.id')->firstOrFail();
        $mahasiswa = User::where('email', 'mahasiswa@kampus.ac.id')->firstOrFail();
        $category = TicketCategory::firstOrFail();

        $this->actingAs($superuser)->post(route('admin.hierarchy.store'), [
            'name' => 'Admin IT Progres',
            'badge_color' => 'indigo',
            'description' => 'Memantau progres staff.',
            'can_create_ticket' => 1,
            'can_receive_ticket' => 1,
            'head_index' => 0,
            'staff' => [
                ['name' => 'Kepala Progres', 'email' => 'kepala.progres@kampus.ac.id', 'keahlian' => 'Koordinasi'],
                ['name' => 'Staff Progres', 'email' => 'staff.progres@kampus.ac.id', 'keahlian' => 'Aplikasi'],
            ],
        ])->assertRedirect();

        $role = Role::where('name', 'Admin IT Progres')->firstOrFail();
        $head = User::where('email', 'kepala.progres@kampus.ac.id')->firstOrFail();
        $staff = User::where('email', 'staff.progres@kampus.ac.id')->firstOrFail();

        $ticket = Ticket::create([
            'ticket_number' => 'TKT-TEST-9100',
            'title' => 'Perbaikan modul nilai',
            'description' => 'Beberapa bagian modul nilai tidak berjalan.',
            'category_id' => $category->id,
            'workflow_type' => 'escalation',
            'creator_id' => $mahasiswa->id,
            'creator_role_id' => $mahasiswa->role_id,
            'current_role_id' => $role->id,
            'assigned_user_id' => $staff->id,
            'status' => 'in_progress',
        ]);

        $this->actingAs($staff)->post(route('tickets.problems.store', $ticket), [
            'title' => "Login gagal\nNilai hilang",
        ])->assertRedirect()->assertSessionHas('error');

        $this->actingAs($head)->post(route('tickets.problems.store', $ticket), [
            'title' => "Login gagal\nNilai hilang",
        ])->assertRedirect()->assertSessionHas('success');

        $ticket->refresh()->load('problems');
        $this->assertNull($ticket->progressPercent());
        $this->assertCount(2, $ticket->problems);

        $this->actingAs($head)->post(route('tickets.progress', $ticket), [
            'progress_percent' => 50,
        ])->assertRedirect()->assertSessionHas('error');

        $this->actingAs($staff)->post(route('tickets.progress', $ticket), [
            'progress_percent' => 50,
            'note' => 'Masih ada kendala akses database.',
        ])->assertRedirect()->assertSessionHas('success');

        $ticket->refresh();
        $this->assertSame(50, $ticket->progressPercent());

        $this->actingAs($head)->get(route('tickets.inbox'))
            ->assertOk()
            ->assertDontSee('Progres Staff')
            ->assertDontSee('Sesuai & bagikan')
            ->assertSee('Staff Progres')
            ->assertSee('1 tiket sedang dikerjakan')
            ->assertSee('Detail')
            ->assertSee('Perbaikan modul nilai')
            ->assertSee('50%')
            ->assertSee('Masih ada kendala akses database.');

        $ticket->forceFill([
            'priority_rank' => 4,
            'priority' => 'low',
            'created_at' => now()->subMinutes(2),
        ])->save();
        $urgent = Ticket::create([
            'ticket_number' => 'TKT-TEST-9101',
            'title' => 'Gangguan jaringan urgent',
            'description' => 'Jaringan kampus putus.',
            'category_id' => $category->id,
            'workflow_type' => 'escalation',
            'creator_id' => $mahasiswa->id,
            'creator_role_id' => $mahasiswa->role_id,
            'current_role_id' => $role->id,
            'assigned_user_id' => $staff->id,
            'priority_rank' => 1,
            'priority' => 'urgent',
            'status' => 'in_progress',
        ]);

        $inboxHtml = $this->actingAs($head)->get(route('tickets.inbox'))->assertOk()->getContent();
        $urgentPos = strpos($inboxHtml, 'Gangguan jaringan urgent');
        $laterPos = strpos($inboxHtml, 'Perbaikan modul nilai');
        $this->assertNotFalse($urgentPos);
        $this->assertNotFalse($laterPos);
        $this->assertLessThan($urgentPos, $laterPos);
        $this->assertStringContainsString('belum dinilai', $inboxHtml);
        $this->assertStringContainsString('Usulan pemohon', $inboxHtml);

        $this->actingAs($head)->post(route('tickets.priority', $urgent), [
            'assessed_priority_rank' => 1,
        ])->assertRedirect()->assertSessionHas('success');
        $this->actingAs($head)->post(route('tickets.priority', $ticket), [
            'assessed_priority_rank' => 4,
        ])->assertRedirect()->assertSessionHas('success');

        $urgent->refresh();
        $ticket->refresh();
        $this->assertSame(1, (int) $urgent->priority_rank);
        $this->assertSame(1, (int) $urgent->assessed_priority_rank);
        $this->assertSame(4, (int) $ticket->assessed_priority_rank);
        $this->assertSame($staff->id, (int) $urgent->assigned_user_id);

        $orderedHtml = $this->actingAs($head)->get(route('tickets.inbox'))->assertOk()->getContent();
        $this->assertLessThan(
            strpos($orderedHtml, 'Perbaikan modul nilai'),
            strpos($orderedHtml, 'Gangguan jaringan urgent')
        );

        $this->actingAs($staff)->post(route('tickets.action', $ticket), [
            'action_type' => 'in_progress',
            'progress_percent' => 60,
            'note' => 'baru segini pak',
        ])->assertRedirect()->assertSessionHas('success');

        $ticket->refresh();
        $this->assertSame(60, $ticket->progressPercent());

        $this->actingAs($head)->get(route('tickets.inbox'))
            ->assertOk()
            ->assertSee('60%');

        $this->actingAs($staff)->post(route('tickets.progress', $ticket), [
            'progress_percent' => 60,
            'work_status' => 'blocked',
        ])->assertRedirect()->assertSessionHas('error');

        $this->actingAs($staff)->post(route('tickets.progress', $ticket), [
            'progress_percent' => 60,
            'work_status' => 'blocked',
            'note' => 'Akses database ditolak.',
        ])->assertRedirect()->assertSessionHas('success');

        $ticket->refresh();
        $this->assertSame('blocked', $ticket->work_status);
        $this->assertSame('in_progress', $ticket->status);

        $this->actingAs($head)->get(route('tickets.inbox'))
            ->assertOk()
            ->assertSee('Blocked')
            ->assertSee('Akses database ditolak.');

        $this->actingAs($staff)->post(route('tickets.progress', $ticket), [
            'progress_percent' => 60,
            'work_status' => 'finished',
            'note' => 'Pengerjaan sudah selesai.',
        ])->assertRedirect()->assertSessionHas('success');

        $ticket->refresh();
        $this->assertSame('finished', $ticket->work_status);
        $this->assertSame('in_progress', $ticket->status);

        $this->actingAs($staff)->get(route('tickets.show', $ticket))
            ->assertOk()
            ->assertSee('Delivered')
            ->assertSee('In progress')
            ->assertSee('Blocked')
            ->assertSee('Finished')
            ->assertSee('Sudah dikerjakan (%)')
            ->assertDontSee('Tindak Lanjut Role')
            ->assertDontSee('Setujui Final');

        $this->actingAs($head)->get(route('tickets.show', $ticket))
            ->assertOk()
            ->assertSee('Masalah & Progres')
            ->assertSee('Tindak Lanjut Role')
            ->assertSee('Login gagal')
            ->assertSee('50%')
            ->assertDontSee('Sudah dikerjakan (%)');

        $this->actingAs($head)->post(route('tickets.action', $ticket), [
            'action_type' => 'resolve',
            'note' => 'Pekerjaan modul nilai sudah selesai.',
        ])->assertRedirect();

        $this->actingAs($head)->get(route('tickets.inbox'))
            ->assertOk()
            ->assertDontSee('Perbaikan modul nilai');

        $this->actingAs($head)->get(route('tickets.history'))
            ->assertOk()
            ->assertSee('Perbaikan modul nilai')
            ->assertSee('Edit');

        $this->actingAs($staff)->post(route('tickets.reopen', $ticket), [
            'note' => 'Saya mau ubah sendiri.',
        ])->assertRedirect()->assertSessionHas('error');

        $this->actingAs($head)->post(route('tickets.reopen', $ticket), [
            'note' => 'Nilai transkrip masih kosong.',
        ])->assertRedirect(route('tickets.show', $ticket));

        $ticket->refresh();
        $this->assertSame('in_progress', $ticket->status);
        $this->assertNull($ticket->resolved_at);

        $this->actingAs($head)->get(route('tickets.inbox'))
            ->assertOk()
            ->assertSee('Perbaikan modul nilai');

        $this->actingAs($mahasiswa)->get(route('tickets.history'))
            ->assertOk()
            ->assertDontSee('Perbaikan modul nilai');
    }

    public function test_employees_sub_sidebar_and_it_dummy_workflow_work_end_to_end(): void
    {
        $superuser = User::where('email', 'superuser@kampus.ac.id')->firstOrFail();
        $supervisorIt = User::where('email', 'supervisor.it@kampus.ac.id')->firstOrFail();
        $adminItLead = User::where('email', 'admin.it@kampus.ac.id')->firstOrFail();
        $staffJaringan = User::where('email', 'fajar.jaringan@kampus.ac.id')->firstOrFail();
        $staffAplikasi = User::where('email', 'nadia.aplikasi@kampus.ac.id')->firstOrFail();
        $staffLab = User::where('email', 'staff.it@kampus.ac.id')->firstOrFail();

        // 1. Halaman Manajemen Pengguna role IT memuat personel IT
        $this->actingAs($superuser)->get(route('admin.users.role', $adminItLead->role->slug))
            ->assertOk()
            ->assertSee($adminItLead->role->name, false)
            ->assertSee($adminItLead->name, false);

        // 2. Request IT baru (TKT-202610-0005) masuk ke Inbox Supervisor IT lengkap dengan gambar bukti
        $incomingItTicket = Ticket::where('ticket_number', 'TKT-202610-0005')->firstOrFail();
        $this->actingAs($supervisorIt)->get(route('tickets.inbox'))
            ->assertOk()
            ->assertSee('Error 502 Bad Gateway Saat Sinkronisasi KRS di Portal SIAKAD')
            ->assertSee('Fajar Nugroho, S.T.')
            ->assertSee('70%');

        // 3. Supervisor IT menetapkan priority, menginput poin pekerjaan beserta bobot % (30%, 50%, 20%), dan men-deliver ke Staff Aplikasi (Nadia)
        $this->actingAs($supervisorIt)->post(route('tickets.action', $incomingItTicket), [
            'action_type' => 'in_progress',
            'priority_rank' => 1,
            'assigned_user_id' => $staffAplikasi->id,
            'problem_items' => [
                ['title' => 'Cek log error PHP-FPM & Nginx server SIAKAD', 'weight_percent' => 30],
                ['title' => 'Optimasi query sinkronisasi KRS & restart worker', 'weight_percent' => 50],
                ['title' => 'Uji akses halaman KRS bersama bagian Akademik', 'weight_percent' => 20],
            ],
            'note' => 'Tolong segera dikerjakan sesuai urutan poin prioritas.',
        ])->assertRedirect()->assertSessionHas('success');

        $incomingItTicket->refresh()->load('problems');
        $this->assertSame($staffAplikasi->id, (int) $incomingItTicket->assigned_user_id);
        $this->assertCount(3, $incomingItTicket->problems);
        $this->assertSame(0, $incomingItTicket->progressPercent());

        // 4. Staff (Nadia) membuka tiket, mencentang poin pertama (30%) -> progres otomatis menjadi 30%, lalu mencentang poin kedua (50%) -> progres otomatis menjadi 80%
        $firstPoint = $incomingItTicket->problems[0];
        $secondPoint = $incomingItTicket->problems[1];

        $this->actingAs($staffAplikasi)->post(route('tickets.problems.toggle', [$incomingItTicket, $firstPoint]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $incomingItTicket->refresh();
        $this->assertSame(30, $incomingItTicket->progressPercent());

        $this->actingAs($staffAplikasi)->post(route('tickets.problems.toggle', [$incomingItTicket, $secondPoint]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $incomingItTicket->refresh();
        $this->assertSame(80, $incomingItTicket->progressPercent());

        // 5. Supervisor IT melihat progres pengerjaan Nadia (80%) secara real-time di Inbox Supervisor IT
        $this->actingAs($supervisorIt)->get(route('tickets.inbox'))
            ->assertOk()
            ->assertSee('Nadia Kusuma, S.Kom.')
            ->assertSee('80%');
    }

    public function test_head_sees_monthly_work_report_and_requester_sees_division_workload(): void
    {
        $head = User::where('email', 'admin.it@kampus.ac.id')->firstOrFail();
        $staff = User::where('email', 'fajar.jaringan@kampus.ac.id')->firstOrFail();
        $pemohon = User::where('email', 'kaprodi@kampus.ac.id')->firstOrFail();
        $role = Role::where('name', 'Admin IT')->firstOrFail();
        $category = TicketCategory::firstOrFail();

        Ticket::create([
            'ticket_number' => 'TKT-TEST-9200',
            'title' => 'Perbaikan jaringan lab',
            'description' => 'Kabel lab putus.',
            'category_id' => $category->id,
            'workflow_type' => 'escalation',
            'creator_id' => $pemohon->id,
            'creator_role_id' => $pemohon->role_id,
            'current_role_id' => $role->id,
            'assigned_user_id' => $staff->id,
            'status' => 'in_progress',
            'work_status' => 'blocked',
            'progress_percent' => 40,
            'progress_note' => 'Catatan internal jangan tampil',
            'delivered_at' => now(),
        ]);

        Ticket::create([
            'ticket_number' => 'TKT-TEST-9202',
            'title' => 'Tiket sedang dikerjakan',
            'description' => 'Sudah lewat tahap delivered.',
            'category_id' => $category->id,
            'workflow_type' => 'escalation',
            'creator_id' => $pemohon->id,
            'creator_role_id' => $pemohon->role_id,
            'current_role_id' => $role->id,
            'assigned_user_id' => $staff->id,
            'status' => 'in_progress',
            'work_status' => 'in_progress',
            'progress_percent' => 25,
            'delivered_at' => now(),
        ]);

        $this->actingAs($head)->get(route('tickets.report', ['month' => now()->format('Y-m')]))
            ->assertOk()
            ->assertSee('Laporan Bulanan')
            ->assertSee('Ekspor')
            ->assertSee('Fajar Nugroho, S.T.')
            ->assertSee('Blocked')
            ->assertSee('Perbaikan jaringan lab')
            ->assertSee('40%')
            ->assertSee('Catatan internal jangan tampil')
            ->assertSee('Tiket sedang dikerjakan')
            ->assertSee('Status bulan ini: In progress')
            ->assertSee('Status bulan ini: Blocked');

        $export = $this->actingAs($head)->get(route('tickets.report.export', ['month' => now()->format('Y-m')]));
        $export->assertOk();
        $csv = $export->streamedContent();
        $this->assertStringContainsString('Perbaikan jaringan lab', $csv);
        $this->assertStringContainsString('Blocked', $csv);
        $this->assertStringContainsString('Fajar Nugroho, S.T.', $csv);
        $this->assertStringContainsString('Catatan internal jangan tampil', $csv);
        $this->assertStringContainsString('Status bulan ini', $csv);
        $this->assertStringContainsString('Request date', $csv);
        $this->assertStringContainsString('ss:Width="300"', $csv);
        $this->assertStringContainsString('application/vnd.ms-excel', (string) $export->headers->get('content-type'));

        $this->actingAs($staff)->get(route('tickets.report'))
            ->assertOk()
            ->assertSee('Kepala/SPV divisi atau kepala role')
            ->assertDontSee('Perbaikan jaringan lab')
            ->assertDontSee('Ekspor');

        $this->actingAs($staff)->get(route('tickets.report.export'))
            ->assertForbidden();

        $finishedTicket = Ticket::create([
            'ticket_number' => 'TKT-TEST-9201',
            'title' => 'Tiket yang sudah selesai',
            'description' => 'Pekerjaan selesai.',
            'category_id' => $category->id,
            'workflow_type' => 'escalation',
            'creator_id' => $pemohon->id,
            'creator_role_id' => $pemohon->role_id,
            'current_role_id' => $role->id,
            'assigned_user_id' => $staff->id,
            'status' => 'in_progress',
            'work_status' => 'finished',
            'finished_at' => now()->subDays(2),
            'progress_percent' => 100,
            'achievement_points' => 8,
        ]);

        \Illuminate\Support\Facades\DB::table('ticket_logs')->insert([
            [
                'ticket_id' => $finishedTicket->id,
                'user_id' => $staff->id,
                'from_role_id' => $role->id,
                'to_role_id' => $role->id,
                'action' => 'progress_reported',
                'note' => 'Staff melaporkan progres pengerjaan 10%. Status: In Progress.',
                'created_at' => now()->subDay(),
                'updated_at' => now()->subDay(),
            ],
            [
                'ticket_id' => $finishedTicket->id,
                'user_id' => $staff->id,
                'from_role_id' => $role->id,
                'to_role_id' => $role->id,
                'action' => 'progress_reported',
                'note' => 'Staff melaporkan progres pengerjaan 60%. Status: In Progress.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $lower = User::where('email', 'bagas.hardware@kampus.ac.id')->firstOrFail();
        Ticket::create([
            'ticket_number' => 'TKT-TEST-9203',
            'title' => 'Tiket poin lebih kecil',
            'description' => 'Poin lebih sedikit.',
            'category_id' => $category->id,
            'workflow_type' => 'escalation',
            'creator_id' => $pemohon->id,
            'creator_role_id' => $pemohon->role_id,
            'current_role_id' => $role->id,
            'assigned_user_id' => $lower->id,
            'status' => 'in_progress',
            'work_status' => 'finished',
            'finished_at' => now(),
            'progress_percent' => 100,
            'achievement_points' => 3,
        ]);

        $workload = $this->actingAs($pemohon)->get(route('tickets.workload'));
        $workload->assertOk()
            ->assertSee('Pekerjaan Divisi')
            ->assertDontSee('Ranking achievement')
            ->assertDontSee('Kontribusi pengerjaan kamu')
            ->assertSee('Admin IT')
            ->assertSee('Perbaikan jaringan lab')
            ->assertSee('Blocked')
            ->assertSee('Fajar Nugroho, S.T.')
            ->assertSee('Tiket yang sudah selesai')
            ->assertSee('Selesai')
            ->assertDontSee('Catatan internal jangan tampil');
        $this->assertSame(0, substr_count($workload->getContent(), 'rank-bar rank-bar-1'));

        $headBoard = $this->actingAs($head)->get(route('tickets.workload'));
        $headBoard->assertOk()
            ->assertSee('Ranking achievement')
            ->assertSee('Staff IT, diurutkan dari poin tiket yang sudah selesai.')
            ->assertSeeInOrder(['Fajar Nugroho, S.T.', '8 poin', 'Bagas Setiawan, A.Md.Kom.', '3 poin', 'Nadia Kusuma, S.Kom.', '0 poin', 'Kontribusi pengerjaan kamu'])
            ->assertSee('Hijau muda ≈ 10% progres');
        $this->assertSame(1, substr_count($headBoard->getContent(), 'rank-bar rank-bar-1'));

        $staffBoard = $this->actingAs($staff)->get(route('tickets.workload'));
        $staffBoard->assertOk()
            ->assertSee('Peringkat kamu.')
            ->assertSee('Kamu')
            ->assertSee('8 poin')
            ->assertSeeInOrder(['Peringkat kamu.', 'Kontribusi pengerjaan kamu'])
            ->assertSee('Hijau muda ≈ 10% progres')
            ->assertSee('contrib-cell contrib-1')
            ->assertSee('contrib-cell contrib-3')
            ->assertSee('progres 10%')
            ->assertSee('hari dikerjakan')
            ->assertDontSee('3 poin');
        $this->assertGreaterThan(300, substr_count($staffBoard->getContent(), 'contrib-cell'));
        $this->assertGreaterThanOrEqual(2, substr_count($staffBoard->getContent(), 'contrib-cell contrib-1'));
        $this->assertGreaterThanOrEqual(2, substr_count($staffBoard->getContent(), 'contrib-cell contrib-3'));
    }

    public function test_finished_work_is_counted_in_the_month_it_was_finished(): void
    {
        $head = User::where('email', 'admin.it@kampus.ac.id')->firstOrFail();
        $staff = User::where('email', 'fajar.jaringan@kampus.ac.id')->firstOrFail();
        $pemohon = User::where('email', 'kaprodi@kampus.ac.id')->firstOrFail();
        $role = Role::where('name', 'Admin IT')->firstOrFail();
        $category = TicketCategory::firstOrFail();

        $ticket = Ticket::create([
            'ticket_number' => 'TKT-TEST-9300',
            'title' => 'Tiket lintas bulan',
            'description' => 'Delivered Oktober, selesai Desember.',
            'category_id' => $category->id,
            'workflow_type' => 'escalation',
            'creator_id' => $pemohon->id,
            'creator_role_id' => $pemohon->role_id,
            'current_role_id' => $role->id,
            'assigned_user_id' => $staff->id,
            'status' => 'in_progress',
            'work_status' => 'finished',
            'progress_percent' => 100,
        ]);
        $ticket->forceFill([
            'delivered_at' => '2026-10-15 09:00:00',
            'finished_at' => '2026-12-02 09:00:00',
            'created_at' => '2026-10-15 09:00:00',
            'updated_at' => '2026-12-02 09:00:00',
        ])->save();

        $this->actingAs($head)->get(route('tickets.report', ['month' => '2026-10']))
            ->assertOk()
            ->assertSee('Tiket lintas bulan')
            ->assertSee('Status bulan ini: Delivered')
            ->assertDontSee('Status bulan ini: Finished');

        $this->actingAs($head)->get(route('tickets.report', ['month' => '2026-12']))
            ->assertOk()
            ->assertSee('Tiket lintas bulan')
            ->assertSee('Status bulan ini: Finished')
            ->assertDontSee('Status bulan ini: Delivered');
    }

    public function test_clean_table_ui_supports_inline_priority_change_and_admin_it_triage(): void
    {
        $adminIt = User::where('email', 'admin.it@kampus.ac.id')->firstOrFail();
        $supervisorIt = User::where('email', 'supervisor.it@kampus.ac.id')->firstOrFail();
        $mahasiswa = User::where('email', 'mahasiswa@kampus.ac.id')->firstOrFail();
        $category = TicketCategory::firstOrFail();

        $ticket = Ticket::create([
            'ticket_number' => 'TKT-TEST-ADMIN-01',
            'title' => 'Kendala printer dan scanner lab',
            'description' => 'Printer tidak merespon di lab komputasi.',
            'category_id' => $category->id,
            'workflow_type' => 'escalation',
            'creator_id' => $mahasiswa->id,
            'creator_role_id' => $mahasiswa->role_id,
            'current_role_id' => $adminIt->role_id,
            'status' => 'open',
            'priority' => 'low',
        ]);

        // 1. Inbox memuat UI clean dengan stat cards (Open Tickets, Pending Response) & table
        $this->actingAs($adminIt)->get(route('tickets.inbox'))
            ->assertOk()
            ->assertSee('Open Tickets')
            ->assertSee('Pending Response')
            ->assertSee('Set Prioritas')
            ->assertSee($ticket->ticket_number);

        // 2. Admin IT dapat mengubah priority langsung via AJAX JSON
        $response = $this->actingAs($adminIt)->postJson(route('tickets.priority', $ticket), [
            'priority' => 'urgent',
        ]);
        $response->assertOk()
            ->assertJson([
                'success' => true,
                'priority' => 'urgent',
            ]);

        $ticket->refresh();
        $this->assertSame('urgent', $ticket->priority);
        $this->assertSame(1, (int) $ticket->priority_rank);
        $this->assertSame(1, (int) $ticket->assessed_priority_rank);

        // 3. Filter priority di Inbox bekerja
        $this->actingAs($adminIt)->get(route('tickets.inbox', ['priority' => 'urgent']))
            ->assertOk()
            ->assertSee($ticket->ticket_number);
    }

    public function test_role_based_mvc_dashboards_render_correctly_for_staff_admin_and_head(): void
    {
        $adminIt = User::where('email', 'admin.it@kampus.ac.id')->firstOrFail();
        $supervisorIt = User::where('email', 'supervisor.it@kampus.ac.id')->firstOrFail();
        $staff = User::where('email', 'fajar.jaringan@kampus.ac.id')->firstOrFail();

        // 1. Staff Pelaksana (Fajar) melihat dashboard personal tiketnya, bukan widget kepala yang kosong
        $this->actingAs($staff)->get(route('tickets.inbox'))
            ->assertOk()
            ->assertSee('Dashboard Staff IT — Antrean Pekerjaan Saya')
            ->assertSee('Tiket Aktif Saya')
            ->assertSee('Profil & Beban Kerja')
            ->assertSee('Detail')
            ->assertDontSee('Belum ada staff yang mengerjakan tiket');

        // 2. Admin IT melihat dashboard Helpdesk dengan kapasitas staf IT dan tombol Detail
        $this->actingAs($adminIt)->get(route('tickets.inbox'))
            ->assertOk()
            ->assertSee('Dashboard Admin IT — Manajemen & Triase Seluruh Tiket')
            ->assertSee('Kapasitas & Beban Kerja Tim Staff')
            ->assertSee('Detail');

        // 3. Kepala Staff / Supervisor IT melihat dashboard pengawasan dengan Staff Progres dan Detail
        $this->actingAs($supervisorIt)->get(route('tickets.inbox'))
            ->assertOk()
            ->assertSee('Dashboard Kepala Staff — Antrean & Progres Tim')
            ->assertSee('Staff Progres')
            ->assertSee('Detail');
    }

    public function test_dedicated_staff_progress_page_and_sidebar_menu(): void
    {
        $supervisorIt = User::where('email', 'supervisor.it@kampus.ac.id')->firstOrFail();
        $adminIt = User::where('email', 'admin.it@kampus.ac.id')->firstOrFail();

        // 1. Menu 'Staff Progres' tampil di sidebar untuk supervisor dan admin IT
        $this->actingAs($supervisorIt)->get(route('tickets.inbox'))
            ->assertOk()
            ->assertSee(route('tickets.staff-progress'));

        // 2. Akses halaman khusus Staff Progres
        $response = $this->actingAs($supervisorIt)->get(route('tickets.staff-progress'));
        $response->assertOk()
            ->assertSee('Staff Progres')
            ->assertSee('Total Tim Staf')
            ->assertSee('Daftar Tiket yang Sedang Dikerjakan Seluruh Staf')
            ->assertSee('Detail');

        // 3. Admin IT juga dapat mengakses halaman Staff Progres
        $this->actingAs($adminIt)->get(route('tickets.staff-progress'))
            ->assertOk()
            ->assertSee('Staff Progres')
            ->assertSee('Total Tim Staf');
    }

    public function test_clean_login_page_renders_and_supports_authentication(): void
    {
        // 1. Halaman login bersih tanpa hardcoded dummy password dan tanpa ratusan user
        $response = $this->get(route('login'));
        $response->assertOk()
            ->assertSee('SuperTicket')
            ->assertSee('Masuk ke Akun')
            ->assertSee('Alamat Email')
            ->assertSee('Kata Sandi')
            ->assertDontSee('Password default akun demo:');

        // 2. Login dengan kredensial yang valid
        $loginResponse = $this->post(route('login.submit'), [
            'email' => 'superuser@kampus.ac.id',
            'password' => 'password',
        ]);
        $loginResponse->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();

        // 3. Akses kembali ke /login saat sudah authenticated akan di-redirect ke dashboard
        $this->get(route('login'))->assertRedirect(route('dashboard'));
    }

    public function test_superadmin_dashboard_refactor_clean_ui_and_removes_role_hierarchy_tree(): void
    {
        $superuser = User::where('email', 'superuser@kampus.ac.id')->firstOrFail();
        $mahasiswa = User::where('email', 'mahasiswa@kampus.ac.id')->firstOrFail();

        // 1. Dashboard Superadmin tampil dengan UI modern & KPI eksekutif (tanpa hirarki role & tanpa pintasan sistem)
        $response = $this->actingAs($superuser)->get(route('dashboard'));
        $response->assertOk()
            ->assertSee('Dashboard Super Admin')
            ->assertSee('Total Tiket')
            ->assertSee('Sedang Diproses')
            ->assertSee('Tiket Selesai')
            ->assertSee('Pengguna Civitas')
            ->assertSee('Tiket Terbaru Seluruh Kampus')
            ->assertSee('Distribusi Status Tiket')
            ->assertSee('Aktivitas Terkini Sistem')
            ->assertDontSee('Struktur Hirarki Role')
            ->assertDontSee('Pintasan Sistem')
            ->assertDontSee(route('admin.users.employees'));

        // 2. Dashboard Mahasiswa / civitas umum juga bersih tanpa Struktur Hirarki Role
        $mhsResponse = $this->actingAs($mahasiswa)->get(route('dashboard'));
        $mhsResponse->assertOk()
            ->assertSee('Tiket Terbaru Saya')
            ->assertDontSee('Struktur Hirarki Role');
    }

    public function test_header_notification_dropdown_alerts_incoming_and_completed_tickets(): void
    {
        $head = User::where('email', 'admin.it@kampus.ac.id')->firstOrFail();
        $staff = User::where('email', 'fajar.jaringan@kampus.ac.id')->firstOrFail();
        $mhs = User::where('email', 'mahasiswa@kampus.ac.id')->firstOrFail();
        $category = TicketCategory::firstOrFail();

        // 1. Buat tiket baru yang ditujukan ke role IT (Tiket Masuk)
        $incomingTicket = Ticket::create([
            'ticket_number' => 'TKT-NOTIF-001',
            'title' => 'Notif Tiket Masuk Baru',
            'description' => 'Kendala pada printer prodi.',
            'category_id' => $category->id,
            'workflow_type' => 'escalation',
            'creator_id' => $mhs->id,
            'creator_role_id' => $mhs->role_id,
            'current_role_id' => $head->role_id,
            'status' => 'open',
            'work_status' => 'not_started',
            'priority' => 'high',
        ]);

        // 2. Buat tiket yang sudah selesai dikerjakan (Tiket Selesai)
        $completedTicket = Ticket::create([
            'ticket_number' => 'TKT-NOTIF-002',
            'title' => 'Notif Tiket Pengerjaan Tuntas',
            'description' => 'Aplikasi sudah di-update.',
            'category_id' => $category->id,
            'workflow_type' => 'escalation',
            'creator_id' => $mhs->id,
            'creator_role_id' => $mhs->role_id,
            'current_role_id' => $head->role_id,
            'assigned_user_id' => $staff->id,
            'status' => 'resolved',
            'work_status' => 'finished',
            'progress_percent' => 100,
            'resolved_at' => now(),
        ]);

        // 3. Kepala IT mengakses halaman dashboard
        $response = $this->actingAs($head)->get(route('dashboard'));
        $response->assertOk()
            ->assertSee('Notifikasi')
            ->assertSee('Semua')
            ->assertSee('Masuk')
            ->assertSee('Selesai');

        // Verifikasi payload hex notifikasi memuat tiket masuk dan tiket selesai
        $html = $response->getContent();
        preg_match("/const hex = '([a-f0-9]+)';/", $html, $matches);
        $this->assertNotEmpty($matches, 'Payload hex notifikasi harus ditemukan di markup header.');

        $decodedJson = hex2bin($matches[1]);
        $this->assertStringContainsString('TKT-NOTIF-001', $decodedJson);
        $this->assertStringContainsString('TKT-NOTIF-002', $decodedJson);
        $this->assertStringContainsString('Tiket Masuk', $decodedJson);
        $this->assertStringContainsString('Tiket Selesai', $decodedJson);
    }

    public function test_user_can_edit_profile_upload_avatar_and_see_it_in_header_and_brand_icon(): void
    {
        Storage::fake('public');

        $user = User::where('email', 'fajar.jaringan@kampus.ac.id')->firstOrFail();

        // 1. Akses halaman edit profil
        $this->actingAs($user)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Pengaturan Profil')
            ->assertSee('Foto Profil')
            ->assertSee('Informasi Pribadi')
            ->assertSee($user->name);

        // 2. Upload foto profil baru dan update informasi pribadi
        $fakePhoto = UploadedFile::fake()->image('fajar-profile.jpg', 300, 300);

        $response = $this->actingAs($user)->post(route('profile.update'), [
            'name' => 'Fajar Nugroho Senior, S.T.',
            'email' => 'fajar.jaringan@kampus.ac.id',
            'phone' => '081234567899',
            'department' => 'Direktorat TI & Jaringan',
            'jabatan' => 'Lead Network Engineer',
            'avatar' => $fakePhoto,
        ]);

        $response->assertRedirect(route('profile.edit'))
            ->assertSessionHas('success');

        $user->refresh();
        $this->assertSame('Fajar Nugroho Senior, S.T.', $user->name);
        $this->assertSame('081234567899', $user->phone);
        $this->assertNotNull($user->avatar_path);
        Storage::disk('public')->assertExists($user->avatar_path);

        // 3. Akses dashboard, pastikan foto profil tampil di pojok kiri atas dan pojok kanan atas
        $dashboardResponse = $this->actingAs($user)->get(route('dashboard'));
        $dashboardResponse->assertOk()
            ->assertSee($user->avatar_url)
            ->assertSee('Keluar (Logout)')
            ->assertSee('Edit Profil');

        // Pastikan endpoint avatar menyajikan file gambar dengan status 200 OK
        $avatarResponse = $this->actingAs($user)->get($user->avatar_url);
        $avatarResponse->assertOk();

        // 4. Pengguna dapat menghapus foto profil kembali ke inisial
        $removeResponse = $this->actingAs($user)->post(route('profile.update'), [
            'name' => 'Fajar Nugroho Senior, S.T.',
            'email' => 'fajar.jaringan@kampus.ac.id',
            'remove_avatar' => 1,
        ]);

        $removeResponse->assertRedirect(route('profile.edit'));
        $user->refresh();
        $this->assertNull($user->avatar_path);
    }

    public function test_create_role_with_head_holder_and_manage_staff_separately(): void
    {
        $superuser = User::where('email', 'superuser@kampus.ac.id')->firstOrFail();

        // 1. Superuser membuat role baru dengan Penanggung Jawab / Kepala Unit (Nama & Email saja)
        $response = $this->actingAs($superuser)->post(route('admin.hierarchy.store'), [
            'name' => 'Unit Sarpras',
            'badge_color' => 'emerald',
            'description' => 'Mengelola inventaris dan sarana prasarana kampus.',
            'can_create_ticket' => 1,
            'can_receive_ticket' => 1,
            'head_name' => 'Bambang Sarpras, M.T.',
            'head_email' => 'bambang.sarpras@kampus.ac.id',
        ]);

        $response->assertRedirect()->assertSessionHas('success');

        $role = Role::where('name', 'Unit Sarpras')->firstOrFail();
        $head = User::where('email', 'bambang.sarpras@kampus.ac.id')->firstOrFail();

        $this->assertEquals($head->id, $role->head_user_id);
        $this->assertEquals($role->id, $head->role_id);
        $this->assertEquals('Kepala Unit Sarpras', $head->jabatan);

        // 2. Akun Kepala Unit dapat langsung login menggunakan email & password 'password'
        $this->post(route('login.submit'), [
            'email' => 'bambang.sarpras@kampus.ac.id',
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($head);

        // 3. Staf teknis tambahan ditambahkan secara terpisah melalui aksi 'Kelola Staf'
        $syncResponse = $this->actingAs($superuser)->put(route('admin.hierarchy.staff.sync', $role), [
            'staff' => [
                ['name' => 'Teknisi Listrik', 'email' => 'listrik@kampus.ac.id', 'keahlian' => 'Kelistrikan'],
            ],
            'head_user_id' => (string) $head->id,
        ]);

        $syncResponse->assertRedirect()->assertSessionHas('success');

        $technician = User::where('email', 'listrik@kampus.ac.id')->firstOrFail();
        $this->assertEquals($role->id, $technician->role_id);
        $this->assertEquals('Kelistrikan', $technician->keahlian);
        $this->assertEquals('Staff Unit Sarpras', $technician->jabatan);

        // Kepala Unit tetap menjadi head_user_id
        $role->refresh();
        $this->assertEquals($head->id, $role->head_user_id);

        // 4. Halaman role menampilkan tombol Hapus role dan memuat email staf
        $indexResponse = $this->actingAs($superuser)->get(route('admin.roles'));
        $indexResponse->assertOk()
            ->assertSee('Kelola Staf')
            ->assertSee('Hapus')
            ->assertSee('bambang.sarpras@kampus.ac.id')
            ->assertSee('listrik@kampus.ac.id');

        // 5. Menghapus staf dari role via route hierarchy.staff.destroy
        $removeStaffResponse = $this->actingAs($superuser)->delete(route('admin.hierarchy.staff.destroy', [$role, $technician]));
        $removeStaffResponse->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseMissing('users', ['id' => $technician->id]);

        // 6. Menghapus role via route hierarchy.destroy
        $destroyRoleResponse = $this->actingAs($superuser)->delete(route('admin.hierarchy.destroy', $role));
        $destroyRoleResponse->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_it_head_sets_ticket_achievement_points_for_the_staff_who_finishes_it(): void
    {
        $head = User::where('email', 'admin.it@kampus.ac.id')->firstOrFail();
        $staff = User::where('email', 'fajar.jaringan@kampus.ac.id')->firstOrFail();
        $pemohon = User::where('email', 'kaprodi@kampus.ac.id')->firstOrFail();
        $role = Role::where('slug', 'admin-it')->firstOrFail();
        $category = TicketCategory::firstOrFail();

        $ticket = Ticket::create([
            'ticket_number' => 'TKT-TEST-9400',
            'title' => 'Tiket berpoin achievement',
            'description' => 'Kepala menentukan poin untuk staff.',
            'category_id' => $category->id,
            'workflow_type' => 'escalation',
            'creator_id' => $pemohon->id,
            'creator_role_id' => $pemohon->role_id,
            'current_role_id' => $role->id,
            'status' => 'open',
        ]);

        $this->actingAs($head)->post(route('tickets.action', $ticket), [
            'action_type' => 'in_progress',
            'assigned_user_id' => $staff->id,
            'achievement_points' => 2,
            'note' => 'Dikerjakan dengan poin achievement.',
        ])->assertRedirect()->assertSessionHas('success');

        $ticket->refresh();
        $this->assertSame(2, (int) $ticket->achievement_points);
        $this->assertSame($staff->id, (int) $ticket->assigned_user_id);

        $this->actingAs($head)->post(route('tickets.achievement', $ticket), [
            'achievement_points' => 11,
        ])->assertSessionHasErrors('achievement_points');

        $this->actingAs($staff)->get(route('tickets.show', $ticket))
            ->assertOk()
            ->assertDontSee('action="'.route('tickets.achievement', $ticket).'"', false);

        $this->actingAs($staff)->post(route('tickets.achievement', $ticket), [
            'achievement_points' => 9,
        ])->assertRedirect()->assertSessionHas('error');

        $this->actingAs($staff)->post(route('tickets.action', $ticket), [
            'action_type' => 'in_progress',
            'achievement_points' => 9,
            'note' => 'Staff mencoba mengubah poin.',
        ])->assertRedirect();

        $ticket->refresh();
        $this->assertSame(2, (int) $ticket->achievement_points);

        $ticket->update([
            'work_status' => 'finished',
            'finished_at' => now(),
        ]);

        $this->actingAs($head)->get(route('tickets.inbox'))
            ->assertOk()
            ->assertSee('2 poin');

        $this->actingAs($staff)->get(route('tickets.inbox'))
            ->assertOk()
            ->assertSee('2 poin');
    }

    public function test_division_sets_head_spv_and_staff_then_service_uses_that_plot(): void
    {
        $superuser = User::where('email', 'superuser@kampus.ac.id')->firstOrFail();

        $this->actingAs($superuser)->post(route('admin.hierarchy.store'), [
            'name' => 'Kepala IT',
            'badge_color' => 'indigo',
            'can_receive_ticket' => 1,
            'head_name' => 'Deny Kepala',
            'head_email' => 'deny.kepala@kampus.ac.id',
        ])->assertRedirect()->assertSessionHas('success');

        $this->actingAs($superuser)->post(route('admin.hierarchy.store'), [
            'name' => 'SPV IT',
            'badge_color' => 'indigo',
            'can_receive_ticket' => 1,
            'head_name' => 'Sari SPV',
            'head_email' => 'sari.spv@kampus.ac.id',
        ])->assertRedirect()->assertSessionHas('success');

        $this->actingAs($superuser)->post(route('admin.hierarchy.store'), [
            'name' => 'Staff IT',
            'badge_color' => 'indigo',
            'can_receive_ticket' => 1,
            'head_name' => 'Nathan Staff',
            'head_email' => 'nathan.staff@kampus.ac.id',
        ])->assertRedirect()->assertSessionHas('success');

        $staffRole = Role::where('name', 'Staff IT')->firstOrFail();
        $nathan = User::where('email', 'nathan.staff@kampus.ac.id')->firstOrFail();
        $this->actingAs($superuser)->put(route('admin.hierarchy.staff.sync', $staffRole), [
            'staff' => [
                ['name' => 'Fajri Staff', 'email' => 'fajri.staff@kampus.ac.id'],
            ],
            'head_user_id' => (string) $nathan->id,
        ])->assertRedirect()->assertSessionHas('success');

        $head = User::where('email', 'deny.kepala@kampus.ac.id')->firstOrFail();
        $spv = User::where('email', 'sari.spv@kampus.ac.id')->firstOrFail();
        $fajri = User::where('email', 'fajri.staff@kampus.ac.id')->firstOrFail();
        $roleCount = Role::count();

        $this->actingAs($superuser)->post(route('admin.hierarchy.divisions.store'), [
            'name' => 'IT',
            'badge_color' => 'indigo',
            'head_user_id' => $head->id,
            'spv_user_id' => $spv->id,
            'staff_user_ids' => [$nathan->id, $head->id],
        ])->assertRedirect()->assertSessionHasErrors('staff_user_ids');

        $this->actingAs($superuser)->post(route('admin.hierarchy.divisions.store'), [
            'name' => 'IT',
            'badge_color' => 'indigo',
            'head_user_id' => $head->id,
            'spv_user_id' => $spv->id,
            'staff_user_ids' => [$nathan->id, $fajri->id],
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame($roleCount, Role::count());

        $division = Division::with(['headRole.head', 'spvRole', 'staffRole', 'members'])->where('name', 'IT')->firstOrFail();
        $this->assertSame('Kepala IT', $division->headRole->name);
        $this->assertSame('SPV IT', $division->spvRole->name);
        $this->assertSame('Staff IT', $division->staffRole->name);
        $this->assertSame($head->role_id, $division->head_role_id);
        $this->assertSame($spv->role_id, $division->spv_role_id);
        $this->assertCount(2, $division->members->where('position', 'staff'));
        $this->assertSame('Deny Kepala', $division->headRole->head->name);

        $this->actingAs($superuser)->get(route('admin.roles'))
            ->assertOk()
            ->assertSee('Manajemen Divisi')
            ->assertSee('Tambah Role Baru')
            ->assertSee('Pilih Kepala')
            ->assertSee('+ Tambah Staff')
            ->assertSee('Puncak: Deny Kepala')
            ->assertDontSee('Kepala Unit & Keahlian Staf')
            ->assertSee('Fajri Staff')
            ->assertSee('Nathan Staff');

        $this->actingAs($superuser)->post(route('admin.categories.store'), [
            'name' => 'SIAKAD',
            'workflow_type' => 'escalation',
            'division_id' => $division->id,
        ])->assertRedirect()->assertSessionHas('success');

        $this->actingAs($superuser)->get(route('admin.categories'))
            ->assertOk()
            ->assertSee('SIAKAD')
            ->assertSee('Divisi IT')
            ->assertSee('Puncak Deny Kepala');

        $category = TicketCategory::where('name', 'SIAKAD')->firstOrFail();
        $this->assertSame($division->id, (int) $category->division_id);
        $this->assertSame($division->workflowRoleIds(), array_map('intval', $category->workflow_roles));
        $this->assertSame($division->staff_role_id, (int) $category->initial_target_role_id);
        $this->assertSame($division->head_role_id, (int) $category->max_approval_role_id);
    }

    public function test_division_accepts_head_spv_and_staff_from_the_same_role(): void
    {
        $superuser = User::where('email', 'superuser@kampus.ac.id')->firstOrFail();

        $this->actingAs($superuser)->post(route('admin.hierarchy.store'), [
            'name' => 'Admin IT Kampus',
            'badge_color' => 'indigo',
            'can_receive_ticket' => 1,
            'head_name' => 'Deny Satu Role',
            'head_email' => 'deny.satu@kampus.ac.id',
        ])->assertRedirect();

        $role = Role::where('name', 'Admin IT Kampus')->firstOrFail();
        $deny = User::where('email', 'deny.satu@kampus.ac.id')->firstOrFail();
        $this->actingAs($superuser)->put(route('admin.hierarchy.staff.sync', $role), [
            'staff' => [
                ['name' => 'Sari Satu Role', 'email' => 'sari.satu@kampus.ac.id'],
                ['name' => 'Nathan Satu Role', 'email' => 'nathan.satu@kampus.ac.id'],
                ['name' => 'Fajri Satu Role', 'email' => 'fajri.satu@kampus.ac.id'],
            ],
            'head_user_id' => (string) $deny->id,
        ])->assertRedirect();

        $spv = User::where('email', 'sari.satu@kampus.ac.id')->firstOrFail();
        $nathan = User::where('email', 'nathan.satu@kampus.ac.id')->firstOrFail();
        $fajri = User::where('email', 'fajri.satu@kampus.ac.id')->firstOrFail();

        $this->actingAs($superuser)->post(route('admin.hierarchy.divisions.store'), [
            'name' => 'IT Kampus',
            'badge_color' => 'indigo',
            'head_user_id' => $deny->id,
            'spv_user_id' => $spv->id,
            'staff_user_ids' => [$nathan->id, $fajri->id],
        ])->assertRedirect()->assertSessionHas('success');

        $division = Division::with('members')->where('name', 'IT Kampus')->firstOrFail();
        $this->assertSame($role->id, $division->head_role_id);
        $this->assertSame($role->id, $division->spv_role_id);
        $this->assertSame($role->id, $division->staff_role_id);
        $this->assertSame([$role->id], $division->workflowRoleIds());
        $this->assertCount(2, $division->members->where('position', 'staff'));

        $role->refresh();
        $this->assertSame($deny->id, (int) $role->head_user_id);

        $this->actingAs($superuser)->post(route('admin.categories.store'), [
            'name' => 'SIAKAD Kampus',
            'workflow_type' => 'escalation',
            'division_id' => $division->id,
        ])->assertRedirect()->assertSessionHas('success');

        $category = TicketCategory::where('name', 'SIAKAD Kampus')->firstOrFail();
        $this->assertSame([$role->id], array_map('intval', $category->workflow_roles));
        $this->assertSame($role->id, (int) $category->max_approval_role_id);
    }

    public function test_create_division_first_then_add_roles_with_accounts_and_configure_service_hierarchy(): void
    {
        $superuser = User::where('email', 'superuser@kampus.ac.id')->firstOrFail();

        // 1. Buat Divisi terlebih dahulu
        $this->actingAs($superuser)->post(route('admin.hierarchy.divisions.store'), [
            'name' => 'Divisi Layanan IT',
            'badge_color' => 'indigo',
            'description' => 'Layanan infrastruktur & sistem IT',
        ])->assertRedirect()->assertSessionHas('success');

        $division = Division::where('name', 'Divisi Layanan IT')->firstOrFail();
        $this->assertSame('Divisi Layanan IT', $division->name);
        $this->assertSame('indigo', $division->badge_color);

        // 2. Tambahkan Role & Akun Email di dalam Divisi tersebut
        // Role Admin/Kepala IT
        $this->actingAs($superuser)->post(route('admin.hierarchy.store'), [
            'division_id' => $division->id,
            'name' => 'Admin Utama IT',
            'badge_color' => 'indigo',
            'can_create_ticket' => 1,
            'can_receive_ticket' => 1,
            'head_name' => 'Budi Admin',
            'head_email' => 'budi.admin@kampus.ac.id',
            'password' => 'password',
        ])->assertRedirect()->assertSessionHas('success');

        // Role SPV IT
        $adminRole = Role::where('name', 'Admin Utama IT')->firstOrFail();
        $this->actingAs($superuser)->post(route('admin.hierarchy.store'), [
            'division_id' => $division->id,
            'name' => 'Supervisor Jaringan',
            'parent_id' => $adminRole->id,
            'badge_color' => 'indigo',
            'can_create_ticket' => 1,
            'can_receive_ticket' => 1,
            'head_name' => 'Citra SPV',
            'head_email' => 'citra.spv@kampus.ac.id',
            'password' => 'password',
        ])->assertRedirect()->assertSessionHas('success');

        // Role Staff IT
        $spvRole = Role::where('name', 'Supervisor Jaringan')->firstOrFail();
        $this->actingAs($superuser)->post(route('admin.hierarchy.store'), [
            'division_id' => $division->id,
            'name' => 'Staff Helpdesk IT',
            'parent_id' => $spvRole->id,
            'badge_color' => 'indigo',
            'can_create_ticket' => 1,
            'can_receive_ticket' => 1,
            'head_name' => 'Doni Helpdesk',
            'head_email' => 'doni.helpdesk@kampus.ac.id',
            'password' => 'password',
        ])->assertRedirect()->assertSessionHas('success');

        $staffRole = Role::where('name', 'Staff Helpdesk IT')->firstOrFail();

        // Pastikan role-role tersebut terhubung ke divisi
        $this->assertSame($division->id, (int) $adminRole->division_id);
        $this->assertSame($division->id, (int) $spvRole->division_id);
        $this->assertSame($division->id, (int) $staffRole->division_id);

        // Pastikan akun user dibuat
        $budi = User::where('email', 'budi.admin@kampus.ac.id')->firstOrFail();
        $this->assertSame($adminRole->id, $budi->role_id);
        $this->assertSame('Divisi Layanan IT', $budi->department);

        // 3. Tampilan Manajemen Divisi memuat divisi dan role-role miliknya
        $this->actingAs($superuser)->get(route('admin.roles'))
            ->assertOk()
            ->assertSee('Divisi Layanan IT')
            ->assertSee('Admin Utama IT')
            ->assertSee('Supervisor Jaringan')
            ->assertSee('Staff Helpdesk IT')
            ->assertSee('budi.admin@kampus.ac.id')
            ->assertSee('citra.spv@kampus.ac.id')
            ->assertSee('doni.helpdesk@kampus.ac.id');

        // 4. Buat Kategori Layanan dengan alur hirarki tersendiri dari role divisi (Staff -> SPV -> Admin)
        $this->actingAs($superuser)->post(route('admin.categories.store'), [
            'name' => 'Layanan Internet & WiFi',
            'workflow_type' => 'tiered_approval',
            'division_id' => $division->id,
            'workflow_roles' => [$staffRole->id, $spvRole->id, $adminRole->id],
        ])->assertRedirect()->assertSessionHas('success');

        $wifiCategory = TicketCategory::where('name', 'Layanan Internet & WiFi')->firstOrFail();
        $this->assertSame($division->id, (int) $wifiCategory->division_id);
        $this->assertSame([$staffRole->id, $spvRole->id, $adminRole->id], array_map('intval', $wifiCategory->workflow_roles));
        $this->assertSame($staffRole->id, (int) $wifiCategory->initial_target_role_id);
        $this->assertSame($adminRole->id, (int) $wifiCategory->max_approval_role_id);

        // 5. Buat Layanan kedua di divisi yang sama dengan alur berbeda (hanya Staff -> Admin, tanpa SPV)
        $this->actingAs($superuser)->post(route('admin.categories.store'), [
            'name' => 'Reset Akun Email Kampus',
            'workflow_type' => 'escalation',
            'division_id' => $division->id,
            'workflow_roles' => [$staffRole->id, $adminRole->id],
        ])->assertRedirect()->assertSessionHas('success');

        $resetCategory = TicketCategory::where('name', 'Reset Akun Email Kampus')->firstOrFail();
        $this->assertSame($division->id, (int) $resetCategory->division_id);
        $this->assertSame([$staffRole->id, $adminRole->id], array_map('intval', $resetCategory->workflow_roles));
        $this->assertSame($staffRole->id, (int) $resetCategory->initial_target_role_id);
        $this->assertSame($adminRole->id, (int) $resetCategory->max_approval_role_id);
    }

    public function test_shared_division_leadership_inbox_and_dispatch_to_staff(): void
    {
        $superuserRole = Role::firstOrCreate(['slug' => 'superuser'], [
            'name' => 'Superuser',
            'is_superuser' => true,
            'can_create_ticket' => true,
            'can_receive_ticket' => true,
        ]);

        $superuser = User::factory()->create([
            'role_id' => $superuserRole->id,
            'email' => 'su.shared@kampus.ac.id',
        ]);

        $mhsRole = Role::firstOrCreate(['slug' => 'mahasiswa'], [
            'name' => 'Mahasiswa',
            'can_create_ticket' => true,
            'can_receive_ticket' => false,
        ]);

        $mhs = User::factory()->create([
            'role_id' => $mhsRole->id,
            'email' => 'mhs.shared@kampus.ac.id',
        ]);

        // 1. Buat Divisi IT
        $this->actingAs($superuser)->post(route('admin.hierarchy.divisions.store'), [
            'name' => 'Divisi Rekomendasi IT',
            'badge_color' => 'indigo',
            'description' => 'Divisi penanganan sistem informasi',
        ])->assertRedirect();

        $division = Division::where('name', 'Divisi Rekomendasi IT')->firstOrFail();

        // 2. Tambah Role & Akun Pimpinan (Kepala Deny & SPV Ardy) serta Staff Syahrul
        $this->actingAs($superuser)->post(route('admin.hierarchy.store'), [
            'division_id' => $division->id,
            'name' => 'Kepala IT',
            'badge_color' => 'indigo',
            'head_name' => 'Pak Deny',
            'head_email' => 'deny.kepala@kampus.ac.id',
            'password' => 'secret123',
            'can_create_ticket' => '1',
            'can_receive_ticket' => '1',
        ])->assertRedirect()->assertSessionHas('success');

        $this->actingAs($superuser)->post(route('admin.hierarchy.store'), [
            'division_id' => $division->id,
            'name' => 'SPV IT',
            'badge_color' => 'indigo',
            'head_name' => 'Pak Ardy',
            'head_email' => 'ardy.spv@kampus.ac.id',
            'password' => 'secret123',
            'can_create_ticket' => '1',
            'can_receive_ticket' => '1',
        ])->assertRedirect()->assertSessionHas('success');

        $this->actingAs($superuser)->post(route('admin.hierarchy.store'), [
            'division_id' => $division->id,
            'name' => 'Staff IT',
            'badge_color' => 'indigo',
            'head_name' => 'Syahrul Pelaksana',
            'head_email' => 'syahrul.staff@kampus.ac.id',
            'password' => 'secret123',
            'can_create_ticket' => '1',
            'can_receive_ticket' => '1',
        ])->assertRedirect()->assertSessionHas('success');

        $headRole = Role::where('name', 'Kepala IT')->firstOrFail();
        $spvRole = Role::where('name', 'SPV IT')->firstOrFail();
        $staffRole = Role::where('name', 'Staff IT')->firstOrFail();

        $deny = User::where('email', 'deny.kepala@kampus.ac.id')->firstOrFail();
        $ardy = User::where('email', 'ardy.spv@kampus.ac.id')->firstOrFail();
        $syahrul = User::where('email', 'syahrul.staff@kampus.ac.id')->firstOrFail();

        // 3. Konfigurasi Kategori Layanan: Tahap 1 Pimpinan (SPV/Kepala), Tahap 2 Staff
        $this->actingAs($superuser)->post(route('admin.categories.store'), [
            'name' => 'Perbaikan SIAKAD Shared',
            'workflow_type' => 'tiered_approval',
            'division_id' => $division->id,
            'workflow_roles' => [$spvRole->id, $staffRole->id],
        ])->assertRedirect()->assertSessionHas('success');

        $category = TicketCategory::where('name', 'Perbaikan SIAKAD Shared')->firstOrFail();
        $this->assertSame($spvRole->id, (int) $category->initial_target_role_id);

        // 4. Mahasiswa Mengajukan Tiket
        $this->actingAs($mhs)->post(route('tickets.store'), [
            'title' => 'Error KRS SIAKAD Tidak Muncul',
            'category_id' => $category->id,
            'description' => 'Menu KRS tidak muncul saat masa pengisian kartu rencana studi.',
            'priority_rank' => 2,
        ])->assertRedirect();

        $ticket = Ticket::where('title', 'Error KRS SIAKAD Tidak Muncul')->firstOrFail();
        $this->assertSame($spvRole->id, (int) $ticket->current_role_id);
        $this->assertSame('open', $ticket->status);

        // 5. SHARED INBOX: Baik SPV Ardy maupun Kepala Deny BISA melihat tiket ini di Inbox mereka!
        $this->actingAs($ardy)->get(route('tickets.inbox'))
            ->assertOk()
            ->assertSee('Error KRS SIAKAD Tidak Muncul');

        $this->actingAs($deny)->get(route('tickets.inbox'))
            ->assertOk()
            ->assertSee('Error KRS SIAKAD Tidak Muncul');

        // 6. SPV Ardy membuka tiket, melihat Syahrul di daftar staf, dan mendisposisikan ke Syahrul
        $showResponse = $this->actingAs($ardy)->get(route('tickets.show', $ticket));
        $showResponse->assertOk();
        $this->assertTrue($ticket->canBeHandledBy($ardy));
        $this->assertTrue($ticket->canBeHandledBy($deny));

        $this->actingAs($ardy)->post(route('tickets.action', $ticket), [
            'action_type' => 'in_progress',
            'assigned_user_id' => $syahrul->id,
            'priority_rank' => 1,
            'note' => 'Tolong dicek query database KRS segera ya Mas Syahrul.',
        ])->assertRedirect();

        $ticket->refresh();
        $this->assertSame('in_progress', $ticket->status);
        $this->assertSame($syahrul->id, (int) $ticket->assigned_user_id);

        // 7. Staf Syahrul melihat tiket di inboxnya dan melaporkan progres pengerjaan
        $this->actingAs($syahrul)->get(route('tickets.inbox'))
            ->assertOk()
            ->assertSee('Error KRS SIAKAD Tidak Muncul');

        $this->actingAs($syahrul)->post(route('tickets.action', $ticket), [
            'action_type' => 'in_progress',
            'progress_percent' => 75,
            'note' => 'Sudah diperbaiki indexing tabel KRS, sedang verifikasi akun mahasiswa.',
        ])->assertRedirect();

        $ticket->refresh();
        $this->assertSame(75, (int) $ticket->progress_percent);

        // 8. Kepala Deny memonitor tiket Syahrul dan melihat progres 75%
        $this->actingAs($deny)->get(route('tickets.show', $ticket))
            ->assertOk()
            ->assertSee('75%')
            ->assertSee('Syahrul Pelaksana');
    }

    public function test_it_division_tickets_triaged_by_head_and_spv_before_admin_it_staff(): void
    {
        $dosenRole = Role::where('slug', 'dosen')->firstOrFail();
        $adminIt = Role::where('slug', 'admin-it')->firstOrFail();
        $mhs = User::where('email', 'mahasiswa@kampus.ac.id')->firstOrFail();
        $staff = User::where('email', 'fajar.jaringan@kampus.ac.id')->firstOrFail();

        $deny = User::factory()->create([
            'name' => 'Pak Deny Triase',
            'email' => 'ushdeny.triase@kampus.ac.id',
            'password' => 'password',
            'role_id' => $dosenRole->id,
        ]);
        $ardy = User::factory()->create([
            'name' => 'Pak Ardy Triase',
            'email' => 'ardy.triase@kampus.ac.id',
            'password' => 'password',
            'role_id' => $dosenRole->id,
        ]);

        $adminIt->update(['division_id' => null]);
        $division = Division::create([
            'name' => 'IT Triase',
            'slug' => 'it-triase',
            'badge_color' => 'indigo',
            'head_role_id' => $dosenRole->id,
            'spv_role_id' => $dosenRole->id,
            'staff_role_id' => $adminIt->id,
        ]);
        $adminIt->update(['division_id' => $division->id]);

        DivisionMember::create(['division_id' => $division->id, 'user_id' => $deny->id, 'position' => 'head']);
        DivisionMember::create(['division_id' => $division->id, 'user_id' => $ardy->id, 'position' => 'spv']);
        DivisionMember::create(['division_id' => $division->id, 'user_id' => $staff->id, 'position' => 'staff']);

        $superuser = User::where('email', 'superuser@kampus.ac.id')->firstOrFail();
        $this->actingAs($superuser)->post(route('admin.categories.store'), [
            'name' => 'SIAKAD Triase',
            'workflow_type' => 'escalation',
            'division_id' => $division->id,
        ])->assertRedirect()->assertSessionHas('success');

        $category = TicketCategory::where('name', 'SIAKAD Triase')->firstOrFail();
        $this->assertSame($adminIt->id, (int) $category->initial_target_role_id);

        $this->actingAs($mhs)->post(route('tickets.store'), [
            'title' => 'SIAKAD Triase Error Login',
            'category_id' => $category->id,
            'description' => 'Mahasiswa tidak bisa login SIAKAD.',
            'priority_rank' => 2,
        ])->assertRedirect();

        $ticket = Ticket::where('title', 'SIAKAD Triase Error Login')->firstOrFail();
        $this->assertSame($adminIt->id, (int) $ticket->current_role_id);
        $this->assertNull($ticket->assigned_user_id);

        // Deny & Ardy melihat antrian; staff Admin IT belum melihat sebelum disposisi
        $this->actingAs($deny)->get(route('tickets.inbox'))
            ->assertOk()
            ->assertSee('SIAKAD Triase Error Login');
        $this->actingAs($ardy)->get(route('tickets.inbox'))
            ->assertOk()
            ->assertSee('SIAKAD Triase Error Login');
        $this->actingAs($staff)->get(route('tickets.inbox'))
            ->assertOk()
            ->assertDontSee('SIAKAD Triase Error Login');

        $this->assertTrue($ticket->canBeHandledBy($deny));
        $this->assertTrue($ticket->canBeDirectedBy($ardy));
        $this->assertFalse($ticket->canBeHandledBy($staff));

        $this->actingAs($ardy)->post(route('tickets.action', $ticket), [
            'action_type' => 'in_progress',
            'assigned_user_id' => $staff->id,
            'priority_rank' => 1,
            'note' => 'Tolong dicek login SIAKAD oleh Fajar.',
        ])->assertRedirect();

        $ticket->refresh();
        $this->assertSame($staff->id, (int) $ticket->assigned_user_id);
        $this->assertSame('in_progress', $ticket->status);

        $this->actingAs($staff)->get(route('tickets.inbox'))
            ->assertOk()
            ->assertSee('SIAKAD Triase Error Login');
    }

    public function test_division_head_and_spv_monitor_admin_it_monthly_report(): void
    {
        $dosenRole = Role::where('slug', 'dosen')->firstOrFail();
        $adminIt = Role::where('slug', 'admin-it')->firstOrFail();
        $category = TicketCategory::firstOrFail();

        $kepala = User::factory()->create([
            'name' => 'Kepala Pantau',
            'email' => 'kepala.pantau@kampus.ac.id',
            'password' => 'password',
            'role_id' => $dosenRole->id,
        ]);
        $spv = User::factory()->create([
            'name' => 'SPV Pantau',
            'email' => 'spv.pantau@kampus.ac.id',
            'password' => 'password',
            'role_id' => $dosenRole->id,
        ]);
        $staff = User::where('email', 'fajar.jaringan@kampus.ac.id')->firstOrFail();

        $adminIt->update(['division_id' => null]);
        $division = Division::create([
            'name' => 'IT Pantau',
            'slug' => 'it-pantau',
            'badge_color' => 'indigo',
            'head_role_id' => $dosenRole->id,
            'spv_role_id' => $dosenRole->id,
            'staff_role_id' => $adminIt->id,
        ]);
        $adminIt->update(['division_id' => $division->id]);

        DivisionMember::create(['division_id' => $division->id, 'user_id' => $kepala->id, 'position' => 'head']);
        DivisionMember::create(['division_id' => $division->id, 'user_id' => $spv->id, 'position' => 'spv']);
        DivisionMember::create(['division_id' => $division->id, 'user_id' => $staff->id, 'position' => 'staff']);

        Ticket::create([
            'ticket_number' => 'TKT-TEST-PANTAU-01',
            'title' => 'Pemantauan Admin IT bulanan',
            'description' => 'Dipantau kepala dan SPV.',
            'category_id' => $category->id,
            'workflow_type' => 'escalation',
            'creator_id' => User::where('email', 'kaprodi@kampus.ac.id')->firstOrFail()->id,
            'creator_role_id' => Role::where('slug', 'kaprodi')->firstOrFail()->id,
            'current_role_id' => $adminIt->id,
            'assigned_user_id' => $staff->id,
            'status' => 'in_progress',
            'work_status' => 'in_progress',
            'progress_percent' => 40,
            'delivered_at' => now(),
        ]);

        $this->actingAs($kepala)->get(route('tickets.report', ['month' => now()->format('Y-m')]))
            ->assertOk()
            ->assertSee('Laporan Bulanan — Admin IT')
            ->assertSee('Pemantauan Admin IT bulanan')
            ->assertSee('Fajar Nugroho, S.T.')
            ->assertSee('Ekspor');

        $this->actingAs($spv)->get(route('tickets.report', ['month' => now()->format('Y-m')]))
            ->assertOk()
            ->assertSee('Laporan Bulanan — Admin IT')
            ->assertSee('Pemantauan Admin IT bulanan')
            ->assertSee('Ekspor');

        $this->actingAs($staff)->get(route('tickets.report'))
            ->assertOk()
            ->assertSee('Kepala/SPV divisi atau kepala role')
            ->assertDontSee('Pemantauan Admin IT bulanan');
    }
}




