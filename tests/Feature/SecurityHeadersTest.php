<?php

use App\Models\User;

/* Security headers (CLAUDE.md §14 Security). */
beforeEach(fn () => seedBasics());

it('sends a CSP with a nonce for the one inline script, and the hardening headers', function () {
    $res = $this->get('/login')->assertOk();
    $csp = (string) $res->headers->get('Content-Security-Policy');

    preg_match("/'nonce-([^']+)'/", $csp, $m);
    expect($csp)->toContain("default-src 'self'")->toContain("object-src 'none'")->toContain("frame-ancestors 'self'")
        ->not->toContain("script-src 'self' 'unsafe-inline'")
        ->and($m[1] ?? null)->not->toBeNull()
        ->and($res->getContent())->toContain('<script nonce="'.$m[1].'">');
    $res->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')->assertHeaderMissing('Strict-Transport-Security');
});

it('adds HSTS only over HTTPS', function () {
    $this->get('https://localhost/login')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});

it('uses a fresh nonce on every request', function () {
    $a = $this->get('/login')->headers->get('Content-Security-Policy');
    $b = $this->get('/login')->headers->get('Content-Security-Policy');
    expect($a)->not->toBe($b);
});

it('has no inline event handlers left in the pages', function () {
    $html = $this->actingAs(userWithRole(User::ROLE_URDC))->get('/urdc/analytics')->getContent();
    expect(preg_match('/\son(change|click|submit|input|load)="/', (string) $html))->toBe(0);
});

it('sends no script-readable XSRF cookie, and forms still carry their token', function () {
    $res = $this->get('/login');
    expect(collect($res->headers->getCookies())->map->getName()->all())->not->toContain('XSRF-TOKEN');
    expect($res->getContent())->toContain('name="_token"');
});
