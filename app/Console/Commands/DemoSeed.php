<?php

namespace App\Console\Commands;

use Database\Seeders\DemoSeeder;
use DomainException;
use Illuminate\Console\Command;

/** Încarcă datele de demonstrație (doar cu DEMO_MODE=true). Idempotent. */
class DemoSeed extends Command
{
    protected $signature = 'app:demo-seed';

    protected $description = 'Încarcă date de demonstrație neutre (petreceri, participanți, bilete, bar) pe o instanță demo';

    public function handle(): int
    {
        try {
            $seeder = app(DemoSeeder::class)->setCommand($this);
            $seeder->run();
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
