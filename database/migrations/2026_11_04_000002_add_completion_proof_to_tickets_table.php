<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('completion_proof_path')->nullable()->after('attachment_name');
            $table->string('completion_proof_name')->nullable()->after('completion_proof_path');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['completion_proof_path', 'completion_proof_name']);
        });
    }
};
