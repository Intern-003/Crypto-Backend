<?php

namespace App\Services\PayoutProviders;

interface PayoutProviderInterface
{
    public function send(array $payload);
}
