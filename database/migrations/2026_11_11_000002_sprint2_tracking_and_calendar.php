<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tickets') && ! Schema::hasColumn('tickets', 'public_token')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->string('public_token', 16)->nullable()->unique()->after('ticket_number');
            });

            $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
            $existing = [];
            DB::table('tickets')->orderBy('id')->select('id')->chunkById(100, function ($rows) use ($alphabet, &$existing) {
                foreach ($rows as $row) {
                    do {
                        $token = '';
                        for ($i = 0; $i < 8; $i++) {
                            $token .= $alphabet[random_int(0, strlen($alphabet) - 1)];
                        }
                    } while (isset($existing[$token]));

                    $existing[$token] = true;
                    DB::table('tickets')->where('id', $row->id)->update(['public_token' => $token]);
                }
            });
        }

        if (! Schema::hasTable('menus')) {
            return;
        }

        $exists = DB::table('menus')->where('slug', 'tickets-calendar')->exists();
        if ($exists) {
            return;
        }

        $menuId = DB::table('menus')->insertGetId([
            'parent_id' => null,
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
            'is_active' => true,
            'sort_order' => 7,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $roleIds = DB::table('roles')->pluck('id');
        foreach ($roleIds as $roleId) {
            DB::table('menu_role')->insertOrIgnore([
                'menu_id' => $menuId,
                'role_id' => $roleId,
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('menus')) {
            $menuId = DB::table('menus')->where('slug', 'tickets-calendar')->value('id');
            if ($menuId) {
                DB::table('menu_role')->where('menu_id', $menuId)->delete();
                DB::table('menus')->where('id', $menuId)->delete();
            }
        }

        if (Schema::hasTable('tickets') && Schema::hasColumn('tickets', 'public_token')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->dropUnique(['public_token']);
                $table->dropColumn('public_token');
            });
        }
    }
};
