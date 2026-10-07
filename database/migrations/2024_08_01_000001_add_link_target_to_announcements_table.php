<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            // DXA: runda 44. Destinație în aplicație pentru butonul anunțului („party:12”, „tickets”, „wallet” …); exclusiv cu `url`.
            $table->string('link_target', 40)->nullable()->after('url_label');
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn('link_target');
        });
    }
};
