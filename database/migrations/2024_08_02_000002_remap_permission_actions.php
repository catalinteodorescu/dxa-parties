<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// DXA: runda 46c — acțiunile sensibile au fost împărțite pe secțiuni (câte o bifă per secțiune). Conturile care aveau
// bifa veche primesc bifele noi corespunzătoare, ca să nu piardă nimic.
return new class extends Migration
{
    private const MAP = [
        'publish' => ['publish_parties', 'publish_announcements'],
        'cancel_sales' => ['cancel_sales', 'cancel_entries'],
        'reports_finalize' => ['finalize_stock_reports', 'finalize_reception_reports'],
        'reports_reopen' => ['reopen_sales', 'reopen_bar_reports', 'reopen_reception_reports'],
        'export_pdf' => ['export_requisitions', 'export_stock_reports', 'export_bar_reports', 'export_reception_reports'],
    ];

    public function up(): void
    {
        foreach (DB::table('admins')->whereNotNull('permissions')->get(['id', 'permissions']) as $row) {
            $data = json_decode((string) $row->permissions, true);
            if (! is_array($data)) {
                continue;
            }

            $actions = [];
            foreach ((array) ($data['actions'] ?? []) as $action) {
                foreach (self::MAP[$action] ?? [$action] as $new) {
                    $actions[$new] = true;
                }
            }
            $data['actions'] = array_keys($actions);

            DB::table('admins')->where('id', $row->id)->update(['permissions' => json_encode($data)]);
        }
    }

    public function down(): void
    {
        // Fără întoarcere: bifele noi sunt mai fine decât cele vechi.
    }
};
