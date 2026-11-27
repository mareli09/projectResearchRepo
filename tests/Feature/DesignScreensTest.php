<?php

use App\Domain\Site\SiteContent;
use App\Models\ResearchRecord;
use App\Models\User;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\SampleRecordSeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\WorkflowSampleSeeder;
use Illuminate\Support\Facades\Storage;

/*
| Every screen of the prototype exists, renders for its role with real records and sample
| workflow data, and is closed to the other roles (CLAUDE.md §4, §15).
*/
beforeEach(function () {
    Storage::fake('research');
    seedBasics();
    $this->seed([SettingsSeeder::class, DemoUserSeeder::class, SampleRecordSeeder::class, WorkflowSampleSeeder::class]);
});

function demo(string $role): User
{
    return User::role($role)->firstOrFail();
}

const SCREENS = [
    User::ROLE_STUDENT => ['/student', '/student/submit', '/student/status', '/student/status?id=SUB-0128', '/student/summary'],
    User::ROLE_ADVISER => ['/adviser', '/adviser/review', '/adviser/review?id=SUB-0139', '/adviser/supervised'],
    User::ROLE_COORDINATOR => ['/coordinator', '/coordinator/screening', '/coordinator/screening?id=SUB-0136&match=2', '/coordinator/themes', '/coordinator/analytics', '/coordinator/analytics?college=CEIS&year=2024'],
    User::ROLE_URDC => ['/urdc/analytics', '/urdc/analytics?college=CEIS&year=2024&theme=3&sdg=4', '/urdc/approvals', '/urdc/requests', '/urdc/clustering', '/urdc/ai-performance', '/site-content'],
    User::ROLE_SYSADMIN => ['/admin/health', '/admin/services', '/admin/settings', '/admin/backups', '/admin/evaluators', '/site-content'],
];

it('opens every screen of each role', function () {
    foreach (SCREENS as $role => $urls) {
        $this->actingAs(demo($role));
        foreach ($urls as $url) {
            $this->get($url)->assertOk();
        }
        foreach (['/search', '/search?q=flood&college=CEIS&sort=views', '/studies/TUA-2023-1420', '/notifications', '/account'] as $url) {
            $this->get($url)->assertOk();
        }
    }
});

it('closes each role’s screens to the other roles', function () {
    foreach (SCREENS as $owner => $urls) {
        foreach (array_diff(array_keys(SCREENS), [$owner]) as $other) {
            $this->actingAs(demo($other));
            foreach ($urls as $url) {
                if ($url === '/site-content' && in_array($other, [User::ROLE_URDC, User::ROLE_SYSADMIN], true)) {
                    continue; // shared by the URDC and the System administrator
                }
                $this->get($url)->assertForbidden();
            }
        }
    }
});

it('sends each role to its own home page from /dashboard', function (string $role, string $home) {
    $this->actingAs(demo($role))->get('/dashboard')->assertRedirect($home);
})->with([
    [User::ROLE_STUDENT, '/student'], [User::ROLE_ADVISER, '/adviser'], [User::ROLE_COORDINATOR, '/coordinator'],
    [User::ROLE_URDC, '/urdc/analytics'], [User::ROLE_SYSADMIN, '/admin/health'],
]);

it('opens every landing page editor tab', function () {
    $this->actingAs(demo(User::ROLE_URDC));
    foreach (array_merge(['brand', 'banner', 'hero'], array_keys(SiteContent::SECTIONS)) as $tab) {
        $this->get('/site-content?tab='.$tab)->assertOk();
    }
});

it('shows the public landing page, catalog and abstracts to guests', function () {
    $this->get('/')->assertOk()
        ->assertSee('Every TUA study, searchable in one place.')
        ->assertSee('Latest approved studies')
        ->assertSee('Explore by SDG');
    $this->get('/catalog')->assertOk()->assertSee('Browse TUA research')->assertSee('1,419 studies');
    $this->get('/catalog?sdg=3')->assertOk();
    $this->get('/catalog?q=telemedicine')->assertOk()->assertSee('Telemedicine');
    $this->get('/catalog/TUA-2023-1420')->assertOk()->assertSee('Capstone Proposal Title Similarity Checker')->assertSee('Sign in');
});

it('keeps signed-in pages away from guests', function (string $url) {
    $this->get($url)->assertRedirect(route('login'));
})->with(['/search', '/studies/TUA-2023-1420', '/notifications', '/student', '/urdc/analytics', '/admin/health']);

it('marks actions that are not built yet as design previews', function () {
    $this->actingAs(demo(User::ROLE_STUDENT))->get('/student/submit')
        ->assertSee('Submit for adviser endorsement')
        ->assertDontSee('data-preview="Sprint 6"', false)
        ->assertSee('LLM suggestion');
    $this->actingAs(demo(User::ROLE_SYSADMIN))->get('/admin/settings')->assertSee('data-preview="Sprint 9"', false);
});

it('filters search by facets on real records', function () {
    $this->actingAs(demo(User::ROLE_ADVISER));
    $all = $this->get('/search')->assertOk();
    expect($all->getContent())->toContain('1,419 studies');

    $ceis = $this->get('/search?college=CEIS')->assertOk()->getContent();
    preg_match('/([\d,]+) studies/', $ceis, $m);
    expect((int) str_replace(',', '', $m[1]))->toBe(ResearchRecord::whereHas('academicUnit', fn ($q) => $q->where('code', 'CEIS'))->count());
});

it('lets advisers download paper files from the study page but not students', function () {
    $this->actingAs(demo(User::ROLE_ADVISER))->get('/studies/TUA-2023-1420')->assertSee('Download');
    $this->actingAs(demo(User::ROLE_STUDENT))->get('/studies/TUA-2023-1420')->assertSee('Request pending')->assertSee('AR-031')->assertDontSee('>Download<', false);
    $this->actingAs(demo(User::ROLE_STUDENT))->get('/studies/TUA-2022-1421')->assertSee('Request access')->assertSee('Send request');
});
