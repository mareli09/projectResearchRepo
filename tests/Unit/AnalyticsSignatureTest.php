<?php

use App\Services\AnalyticsClient\HttpAnalyticsClient;

it('signs "{timestamp}.{body}" the same way the Python service verifies it', function () {
    // Same input as analytics/tests/test_service.py::test_matches_laravel_signature_format
    $headers = HttpAnalyticsClient::sign('{"k":5}', 'secret', 1700000000);

    expect($headers['X-Timestamp'])->toBe('1700000000');
    expect($headers['X-Signature'])->toBe(hash_hmac('sha256', '1700000000.{"k":5}', 'secret'));
});

it('refuses to start without a shared secret', function () {
    new HttpAnalyticsClient('http://127.0.0.1:8001', '');
})->throws(RuntimeException::class);
