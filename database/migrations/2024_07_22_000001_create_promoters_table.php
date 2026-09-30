<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DXA: adaugat (Coduri de reducere - promotori).
 *
 * `promoters`: evidența promotorilor (cine aduce lume la petreceri). Un promotor poate avea coduri la mai multe
 * petreceri; statisticile se strâng pe promotor. Numele e unic (fără diferență între litere mari/mici, verificat în cod).
 *
 * `party_discount_codes.promoter_id` (fără FK, ca `participant_id`) înlocuiește textul liber `promoter`: numele deja
 * introduse se mută în `promoters` (câte unul pe nume distinct, fără diferență între litere mari/mici), apoi coloana veche dispare.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promoters', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('phone', 40)->nullable();
            $table->string('note', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('name');
        });

        Schema::table('party_discount_codes', function (Blueprint $table) {
            $table->unsignedBigInteger('promoter_id')->nullable()->after('code');
            $table->index('promoter_id');
        });

        // Mută textele existente în promoters.
        $ids = [];
        foreach (DB::table('party_discount_codes')->whereNotNull('promoter')->where('promoter', '!=', '')->orderBy('id')->get(['id', 'promoter']) as $row) {
            $name = trim($row->promoter);
            $key = mb_strtolower($name);
            if ($name === '') {
                continue;
            }
            $ids[$key] ??= DB::table('promoters')->insertGetId(['name' => $name, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('party_discount_codes')->where('id', $row->id)->update(['promoter_id' => $ids[$key]]);
        }

        Schema::table('party_discount_codes', function (Blueprint $table) {
            $table->dropColumn('promoter');
        });
    }

    public function down(): void
    {
        Schema::table('party_discount_codes', function (Blueprint $table) {
            $table->string('promoter', 120)->nullable()->after('code');
        });

        foreach (DB::table('promoters')->get(['id', 'name']) as $p) {
            DB::table('party_discount_codes')->where('promoter_id', $p->id)->update(['promoter' => $p->name]);
        }

        Schema::table('party_discount_codes', function (Blueprint $table) {
            $table->dropIndex(['promoter_id']);
            $table->dropColumn('promoter_id');
        });

        Schema::dropIfExists('promoters');
    }
};
