<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Data lama tersimpan saat APP_TIMEZONE=UTC (selisih -7 jam dari WIB).
     * Geser +7 jam sekali agar tampilan sesuai Asia/Jakarta.
     */
    public function up(): void
    {
        $shifts = [
            'tickets' => ['created_at', 'updated_at', 'delivered_at', 'finished_at', 'resolved_at'],
            'ticket_logs' => ['created_at', 'updated_at'],
            'ticket_problems' => ['created_at', 'updated_at', 'done_at'],
        ];

        foreach ($shifts as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                DB::table($table)
                    ->whereNotNull($column)
                    ->update([
                        $column => DB::raw("DATE_ADD(`{$column}`, INTERVAL 7 HOUR)"),
                    ]);
            }
        }
    }

    public function down(): void
    {
        $shifts = [
            'tickets' => ['created_at', 'updated_at', 'delivered_at', 'finished_at', 'resolved_at'],
            'ticket_logs' => ['created_at', 'updated_at'],
            'ticket_problems' => ['created_at', 'updated_at', 'done_at'],
        ];

        foreach ($shifts as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                DB::table($table)
                    ->whereNotNull($column)
                    ->update([
                        $column => DB::raw("DATE_SUB(`{$column}`, INTERVAL 7 HOUR)"),
                    ]);
            }
        }
    }
};
