<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('registrations', 'preselection_token')) {
            Schema::table('registrations', function (Blueprint $table) {
                $table->string('preselection_token', 64)->nullable()->unique()->after('status');
            });
        }

        // Générer un token pour les inscriptions existantes
        DB::table('registrations')->whereNull('preselection_token')->orderBy('id')->each(function ($row) {
            DB::table('registrations')
                ->where('id', $row->id)
                ->update(['preselection_token' => Str::random(64)]);
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropUnique(['preselection_token']);
            $table->dropColumn('preselection_token');
        });
    }
};
