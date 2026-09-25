<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * DXA: adaugat (Setări - Metode de plată).
 *
 * Lista de metode de plată devine unică și se gestionează DOAR din Setări:
 *  - predefinite (nu se șterg): cash, card, transfer, revolut, token, credit;
 *  - custom: adăugate din Setări, cu cheie stabilă `c_<slug>` (nu se schimbă la redenumire).
 *
 * Migrarea datelor existente:
 *  - `parties.payment_methods` (JSON) trece pe chei: `credite` -> `credit`, iar etichetele custom scrise
 *    liber pe petreceri devin metode custom în Setări (fără duplicate, comparate după etichetă);
 *  - tokenii: starea (`token_mode`) se deduce din vechiul `uses_tokens`; petrecerile cu listă completată
 *    primesc `token` în listă (până acum la bar se accepta oricând), ca să nu li se schimbe comportamentul;
 *  - creditele se activează doar dacă apar deja pe o petrecere sau într-o vânzare; „participanții pot cumpăra
 *    credite" pornește dezactivat.
 * `sale_payments.method` nu se modifică: cheile cash/token/credit/benefit rămân valide.
 */
return new class extends Migration
{
    private const BUILTIN = [
        'cash' => 'Cash',
        'card' => 'Card',
        'transfer' => 'Transfer bancar',
        'revolut' => 'Revolut',
        'token' => 'Tokeni',
        'credit' => 'Credite',
    ];

    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            $table->string('label', 60);
            $table->boolean('is_builtin')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();

        // Tokeni: starea se deduce din vechea setare `uses_tokens` (implicit: activi).
        $usesTokensRaw = DB::table('settings')->where('key', 'uses_tokens')->value('value');
        $tokensOn = $usesTokensRaw === null || filter_var($usesTokensRaw, FILTER_VALIDATE_BOOLEAN);

        $parties = DB::table('parties')->select('id', 'payment_methods')->get();

        $creditUsed = DB::table('sale_payments')->where('method', 'credit')->exists();
        foreach ($parties as $p) {
            if (in_array('credite', $this->decode($p->payment_methods), true)) {
                $creditUsed = true;
            }
        }

        foreach (self::BUILTIN as $key => $label) {
            DB::table('payment_methods')->insert([
                'key' => $key,
                'label' => $label,
                'is_builtin' => true,
                'is_active' => match ($key) {
                    'token' => $tokensOn,
                    'credit' => $creditUsed,
                    default => true,
                },
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach ([
            'token_mode' => $tokensOn ? 'active' : 'off',
            'uses_tokens' => $tokensOn ? '1' : '0',
            'credits_purchasable' => '0',
        ] as $key => $value) {
            DB::table('settings')->updateOrInsert(['key' => $key], ['value' => $value, 'created_at' => $now, 'updated_at' => $now]);
        }

        // Petreceri: chei în loc de etichete.
        foreach ($parties as $p) {
            $old = $this->decode($p->payment_methods);
            if ($old === []) {
                continue; // lista goală = nespecificat (rămâne așa)
            }

            $new = [];
            foreach ($old as $m) {
                $new[] = $this->keyFor((string) $m, $now);
            }
            if ($tokensOn) {
                $new[] = 'token';
            }

            DB::table('parties')->where('id', $p->id)->update([
                'payment_methods' => json_encode(array_values(array_unique($new)), JSON_UNESCAPED_UNICODE),
            ]);
        }
    }

    public function down(): void
    {
        // Petrecerile revin la forma veche (ca sa se poata rula up() din nou fara sa strice datele):
        // credit -> credite, metodele custom -> eticheta lor; `token` nu exista in listele vechi.
        $labels = DB::table('payment_methods')->where('is_builtin', false)->pluck('label', 'key')->all();

        foreach (DB::table('parties')->select('id', 'payment_methods')->get() as $p) {
            $list = $this->decode($p->payment_methods);
            if ($list === []) {
                continue;
            }

            $old = [];
            foreach ($list as $key) {
                if ($key === 'token') {
                    continue;
                }
                $old[] = $key === 'credit' ? 'credite' : ($labels[$key] ?? $key);
            }

            DB::table('parties')->where('id', $p->id)->update([
                'payment_methods' => json_encode(array_values(array_unique($old)), JSON_UNESCAPED_UNICODE),
            ]);
        }

        Schema::dropIfExists('payment_methods');
        DB::table('settings')->whereIn('key', ['token_mode', 'credits_purchasable'])->delete();
    }

    /** @return array<int, string> */
    private function decode(?string $json): array
    {
        $decoded = $json ? json_decode($json, true) : null;

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
    }

    /** Cheia unei valori vechi: predefinită, sau metodă custom (creată acum dacă nu există). */
    private function keyFor(string $value, $now): string
    {
        $value = trim($value);

        if ($value === 'credite') {
            return 'credit';
        }
        if (isset(self::BUILTIN[$value])) {
            return $value;
        }

        $lower = mb_strtolower($value);
        foreach (self::BUILTIN as $key => $label) {
            if ($lower === mb_strtolower($label)) {
                return $key;
            }
        }

        $existing = DB::table('payment_methods')->whereRaw('LOWER(label) = ?', [$lower])->value('key');
        if ($existing) {
            return $existing;
        }

        $base = 'c_'.Str::limit(Str::slug($value, '_') ?: 'metoda', 40, '');
        $key = $base;
        for ($i = 2; DB::table('payment_methods')->where('key', $key)->exists(); $i++) {
            $key = $base.'_'.$i;
        }

        DB::table('payment_methods')->insert([
            'key' => $key,
            'label' => Str::limit($value, 60, ''),
            'is_builtin' => false,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $key;
    }
};
