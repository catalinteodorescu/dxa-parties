<?php

namespace App\Contracts;

interface SmsSender
{
    /**
     * Trimite un SMS către un număr de telefon.
     */
    public function send(string $phone, string $message): void;
}
