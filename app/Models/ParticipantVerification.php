<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * DXA: adaugat (Aplicația participanților - runda 1). Înregistrare în așteptare: nume + parola hash-uită + codul SMS
 * (hash-uit, cu expirare și număr limitat de încercări). Participantul se creează / se leagă doar după confirmarea
 * codului (App\Services\ParticipantAccounts::verifyRegistration()).
 */
class ParticipantVerification extends Model
{
    protected $fillable = [
        'phone', 'name', 'password_hash', 'code_hash', 'expires_at', 'attempts', 'send_count', 'last_sent_at',
    ];

    protected $hidden = ['password_hash', 'code_hash'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'last_sent_at' => 'datetime',
        ];
    }
}
