<?php

namespace App\Services\Interfaces;

interface GameVerificationInterface
{
    public function resolveAccount(string $game, string $uid, ?string $server): array;
}
