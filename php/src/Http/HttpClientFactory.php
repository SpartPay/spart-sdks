<?php

declare(strict_types=1);

namespace Spart\Sdk\Http;

interface HttpClientFactory
{
    public function createClient(): HttpClient;
}
