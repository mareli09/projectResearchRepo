<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tests never call real outside services: the local analytics service (it would overwrite
        // the developer's index) or the LLM provider. An unfaked call throws, which the app treats
        // as "service unavailable". Tests that need a service fake its answers with Http::fake().
        Http::preventStrayRequests();
    }
}
