<?php

use App\Models\User;
use Illuminate\Support\Facades\Storage;

/* Installable web app and phone uploads (CLAUDE.md §16). */
beforeEach(fn () => seedBasics());

it('serves a manifest with the names, standalone display, the current accent and both icon sizes', function () {
    $res = $this->get('/manifest.webmanifest')->assertOk()->assertHeader('Content-Type', 'application/manifest+json');
    $m = $res->json();

    expect($m['name'])->toBe('TUA Research Repository')->and($m['short_name'])->toBe('TUA Repository')
        ->and($m['display'])->toBe('standalone')->and($m['theme_color'])->toBe('#1b4a42')
        ->and(collect($m['icons'])->pluck('sizes')->unique()->values()->all())->toBe(['192x192', '512x512']);
    foreach ($m['icons'] as $icon) {
        expect(file_exists(public_path(ltrim($icon['src'], '/'))))->toBeTrue();
        [$w] = getimagesize(public_path(ltrim($icon['src'], '/')));
        expect($w.'x'.$w)->toBe($icon['sizes']);
    }
});

it('links the manifest from the landing page, guest pages and signed-in pages', function () {
    $this->get('/')->assertOk()->assertSee('rel="manifest"', false);
    $this->get('/login')->assertOk()->assertSee('rel="manifest"', false)->assertSee('apple-touch-icon', false);
    $this->actingAs(userWithRole(User::ROLE_URDC))->get('/urdc/analytics')->assertOk()->assertSee('rel="manifest"', false);
});

it('has an offline page that holds no personal data, even when signed in', function () {
    $user = userWithRole(User::ROLE_STUDENT, ['name' => 'Jasmine Villanueva']);
    $this->actingAs($user)->get('/offline')->assertOk()->assertSee('You are offline')
        ->assertDontSee('Jasmine')->assertDontSee($user->email)->assertDontSee('csrf-token', false);
});

it('caches only the app shell in the service worker, never pages or files', function () {
    $sw = file_get_contents(public_path('sw.js'));

    expect($sw)->toContain("'/offline'")->toContain("url.pathname.startsWith('/build/')")
        ->toContain("req.mode === 'navigate'")->toContain("fetch(req).catch(() => caches.match('/offline'))");
    // The only cache.put is for /build/ and /icons/ assets.
    expect(substr_count($sw, 'cache.put('))->toBe(1);
});

it('answers script uploads with the next page, keeping the flash message for it', function () {
    Storage::fake('research');
    $student = userWithRole(User::ROLE_STUDENT, ['academic_unit_id' => ceisId(), 'program_id' => bsitId()]);

    $res = $this->actingAs($student)->withHeader('X-Tua-Upload', '1')
        ->post('/student/submit', ['action' => 'draft', 'title' => 'A draft title for an upload test', 'files' => ['full_paper' => pdfUpload()]])
        ->assertOk();
    expect($res->json('redirect'))->toBe(route('student.submit'));

    // The message was not used up by the script; the page the browser opens next shows it.
    $this->actingAs($student)->get('/student/submit')->assertSee('saved. Only you can see it.');

    // Errors are kept for the next page in the same way.
    $err = $this->actingAs($student)->withHeader('X-Tua-Upload', '1')->post('/student/submit', ['action' => 'draft', 'title' => '', 'files' => ['full_paper' => pdfUpload()]]);
    expect($err->json('redirect'))->not->toBeNull();
});
