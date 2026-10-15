<?php

namespace App\Auth;

use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * DXA: adaugat (runda 74). Ghidul `admin` cu login SEPARAT pe zonă (admin / receptie / bar): aceleași conturi (`admins`), dar fiecare
 * zonă își ține autentificarea în propria cheie de sesiune și propriul cookie „ține-mă conectat”. Astfel, intrarea (sau ieșirea) dintr-o
 * zonă nu o atinge pe celelalte, în același browser. Zona se alege per cerere (middleware `admin.zone`); zona „admin” păstrează
 * exact numele vechi ale cheilor, deci sesiunile deja deschise în panou rămân valabile.
 */
class ZoneGuard extends SessionGuard
{
    public const REMEMBER_MINUTES_APPS = 43200; // 30 de zile pentru Recepție / Bar

    protected string $zone = 'admin';

    /** Utilizator pus explicit (login, lucrător activ, actingAs în teste) — nu se aruncă la schimbarea zonei. */
    protected bool $explicitUser = false;

    /**
     * Alege zona. Dacă un middleware anterior a citit deja utilizatorul din sesiunea ALTEI zone (de pildă contorul de vizite),
     * citirea aceea se aruncă: utilizatorul se reia din cheia zonei alese.
     */
    public function setZone(string $zone): static
    {
        if ($zone !== $this->zone && ! $this->explicitUser) {
            $this->user = null;
            $this->loggedOut = false;
        }

        $this->zone = $zone;
        $this->rememberDuration = $zone === 'admin' ? 576000 : self::REMEMBER_MINUTES_APPS;

        return $this;
    }

    public function setUser(Authenticatable $user): static
    {
        $this->explicitUser = true;

        return parent::setUser($user);
    }

    public function zone(): string
    {
        return $this->zone;
    }

    public function getName(): string
    {
        return 'login_'.$this->zone.'_'.sha1(SessionGuard::class);
    }

    public function getRecallerName(): string
    {
        return 'remember_'.$this->zone.'_'.sha1(SessionGuard::class);
    }

    /**
     * Jetonul „ține-mă conectat” e pe cont (comun zonelor): ieșirea dintr-o zonă nu-l schimbă, ca să nu închidă
     * și conectările de 30 de zile din celelalte zone. Cookie-ul zonei respective se șterge oricum la ieșire.
     */
    protected function cycleRememberToken($user): void {}
}
