<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DXA: adaugat (runda 60). Termeni și condiții în aplicația participanților.
 *
 *  - `terms_versions`: fiecare publicare e o versiune nouă, cu textul întreg (nu se editează după publicare, ca să rămână dovada
 *    a ceea ce a acceptat fiecare). `requires_reaccept` = conturile care n-au acceptat măcar această versiune sunt rugate să o accepte.
 *  - `participants.terms_version_id` / `terms_accepted_at`: ultima versiune acceptată și când.
 *  - `participant_verifications.terms_version_id`: versiunea bifată în formularul de cont nou (înregistrarea se termină abia după codul SMS).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terms_versions', function (Blueprint $table) {
            $table->id();
            $table->longText('body');
            $table->boolean('requires_reaccept')->default(true);
            $table->foreignId('published_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('participants', function (Blueprint $table) {
            $table->unsignedBigInteger('terms_version_id')->nullable()->after('phone_verified_at');
            $table->dateTime('terms_accepted_at')->nullable()->after('terms_version_id');
        });

        Schema::table('participant_verifications', function (Blueprint $table) {
            $table->unsignedBigInteger('terms_version_id')->nullable()->after('password_hash');
        });
    }

    public function down(): void
    {
        Schema::table('participant_verifications', fn (Blueprint $table) => $table->dropColumn('terms_version_id'));
        Schema::table('participants', fn (Blueprint $table) => $table->dropColumn(['terms_version_id', 'terms_accepted_at']));
        Schema::dropIfExists('terms_versions');
    }
};
