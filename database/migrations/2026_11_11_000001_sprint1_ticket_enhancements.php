<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tickets')) {
            Schema::table('tickets', function (Blueprint $table) {
                if (! Schema::hasColumn('tickets', 'due_date')) {
                    $table->date('due_date')->nullable()->after('finished_at');
                }
                if (! Schema::hasColumn('tickets', 'category_other')) {
                    $table->string('category_other')->nullable()->after('category_id');
                }
                if (! Schema::hasColumn('tickets', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }

        if (! Schema::hasTable('ticket_categories')) {
            return;
        }

        $exists = DB::table('ticket_categories')->where('slug', 'other')->exists();
        if ($exists) {
            return;
        }

        $itDivisionId = DB::table('divisions')->where('slug', 'divisi-it')->value('id')
            ?? DB::table('divisions')->where('name', 'like', '%IT%')->value('id');
        $supervisorRoleId = DB::table('roles')->where('slug', 'supervisor')->value('id')
            ?? DB::table('roles')->where('slug', 'supervisor-it')->value('id');

        DB::table('ticket_categories')->insert([
            'name' => 'Other',
            'slug' => 'other',
            'icon' => 'sparkles',
            'color' => 'slate',
            'description' => 'Kategori lain yang diisi sendiri oleh pemohon.',
            'workflow_roles' => null,
            'initial_target_role_id' => $supervisorRoleId,
            'workflow_type' => 'escalation',
            'division_id' => $itDivisionId,
            'max_approval_role_id' => null,
            'template_path' => null,
            'template_name' => null,
            'is_attachment_required' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('tickets')) {
            Schema::table('tickets', function (Blueprint $table) {
                if (Schema::hasColumn('tickets', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('tickets', 'category_other')) {
                    $table->dropColumn('category_other');
                }
                if (Schema::hasColumn('tickets', 'due_date')) {
                    $table->dropColumn('due_date');
                }
            });
        }

        if (Schema::hasTable('ticket_categories')) {
            DB::table('ticket_categories')->where('slug', 'other')->delete();
        }
    }
};
