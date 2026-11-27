<?php

use App\Domain\Audit\Activity;
use App\Domain\Site\SiteContent;
use App\Domain\Site\SiteContentEditor;
use App\Models\SiteContentVersion;
use App\Models\User;
use Database\Seeders\SiteContentSeeder;

/*
| [Not in manuscript] Landing page CMS (CLAUDE.md §8): draft, preview, publish with history,
| discard, defaults. Plain text only.
*/
beforeEach(function () {
    seedBasics();
    $this->seed(SiteContentSeeder::class);
    $this->urdc = userWithRole(User::ROLE_URDC);
});

function heroForm(array $overrides = []): array
{
    $c = SiteContent::published()['hero'];

    return ['f' => array_merge(['hero__eyebrow' => $c['eyebrow'], 'hero__title' => $c['title'], 'hero__lead' => $c['lead'],
        'hero__cta1' => $c['cta1'], 'hero__cta2' => $c['cta2'], 'hero__popular' => $c['popular']], $overrides)];
}

it('saves edits to a draft that visitors do not see until published', function () {
    $this->actingAs($this->urdc)->post('/site-content/hero', heroForm(['hero__title' => 'Find any TUA study in seconds']))
        ->assertRedirect('/site-content?tab=hero');

    expect(SiteContentVersion::where('status', 'draft')->count())->toBe(1);
    auth()->logout();
    $this->get('/')->assertDontSee('Find any TUA study in seconds');

    // Preview shows the draft with a banner, for CMS users only.
    $this->actingAs($this->urdc)->get('/?preview=1')->assertSee('Find any TUA study in seconds')->assertSee('Preview of the draft');
    $this->actingAs(userWithRole(User::ROLE_STUDENT))->get('/?preview=1')->assertDontSee('Find any TUA study in seconds');

    $this->actingAs($this->urdc)->post('/site-content/publish')->assertSessionHas('status', 'Published. Changed: Hero.');
    auth()->logout();
    $this->get('/')->assertSee('Find any TUA study in seconds');

    $v = SiteContentVersion::where('status', 'published')->orderByDesc('version')->first();
    expect($v->version)->toBe(2)->and($v->summary)->toBe('Hero')->and($v->published_by)->toBe($this->urdc->id);
    expect(Activity::where('event', 'cms')->count())->toBe(1);
});

it('strips tags and escapes content, and refuses empty titles', function () {
    $this->actingAs($this->urdc)->post('/site-content/hero', heroForm(['hero__title' => '<script>alert(1)</script>Safe <b>title</b>']));
    expect(SiteContentEditor::working()['hero']['title'])->toBe('alert(1)Safe title');

    $this->actingAs($this->urdc)->post('/site-content/hero', heroForm(['hero__title' => '   ']))->assertSessionHasErrors('f.hero__title');
});

it('adds, removes and reorders list items', function () {
    $faq = SiteContent::published()['faq'];
    $items = array_map(fn ($i) => ['q' => $i['q'], 'a' => $i['a']], $faq['items']);
    $base = ['f' => ['faq__eyebrow' => $faq['eyebrow'], 'faq__title' => $faq['title']]];

    $this->actingAs($this->urdc)->post('/site-content/faq', $base + ['items' => $items, 'add' => '1']);
    expect(SiteContentEditor::working()['faq']['items'])->toHaveCount(count($items) + 1);

    $items[0]['_remove'] = '1';
    $this->actingAs($this->urdc)->post('/site-content/faq', $base + ['items' => $items, 'move' => '1:up']);
    $after = SiteContentEditor::working()['faq']['items'];
    expect($after)->toHaveCount(count($items) - 1)->and($after[0]['q'])->toBe($faq['items'][2]['q']);
});

it('shows, hides and reorders sections, and features chosen studies', function () {
    $this->actingAs($this->urdc)->post('/site-content/layout', ['toggle' => 'news']);
    $this->actingAs($this->urdc)->post('/site-content/layout', ['move' => 'about:up']);
    $c = SiteContentEditor::working();
    expect($c['show']['news'])->toBeFalse()->and($c['order'][0])->toBe('about');

    $r = makeRecord();
    $r->forceFill(['status' => 'published', 'published_at' => now()])->save();
    $this->actingAs($this->urdc)->post('/site-content/featured', ['f' => ['featured__eyebrow' => 'Featured', 'featured__title' => 'Picks', 'featured__intro' => ''],
        'mode' => 'manual', 'count' => 3, 'add_id' => $r->public_id]);
    expect(SiteContentEditor::working()['featured']['ids'])->toContain($r->public_id);
});

it('discards the draft and loads the defaults into a new one', function () {
    $this->actingAs($this->urdc)->post('/site-content/hero', heroForm(['hero__title' => 'Temporary']));
    $this->actingAs($this->urdc)->post('/site-content/discard');
    expect(SiteContentVersion::where('status', 'draft')->exists())->toBeFalse();

    $this->actingAs($this->urdc)->post('/site-content/defaults');
    expect(SiteContentEditor::working()['hero']['title'])->toBe(SiteContent::defaults()['hero']['title']);
});

it('sets the default theme color for the site', function () {
    $this->actingAs($this->urdc)->post('/site-content/brand', ['theme_color' => '#1F5F3F', 'b' => ['theme__allowUser' => '0'],
        'f' => ['site__name' => 'TUA Research Repository', 'site__tagline' => 'URDC', 'footer__text' => '© TUA']]);
    $this->actingAs($this->urdc)->post('/site-content/publish');

    expect(SiteContent::published()['theme'])->toBe(['color' => '#1f5f3f', 'allowUser' => false]);
    $this->actingAs($this->urdc)->post('/site-content/brand', ['theme_color' => 'green'])->assertSessionHasErrors('theme_color');
});

it('is for the URDC and System administrator only', function () {
    $this->actingAs(userWithRole(User::ROLE_COORDINATOR))->post('/site-content/publish')->assertForbidden();
    $this->actingAs(userWithRole(User::ROLE_SYSADMIN))->get('/site-content?tab=faq')->assertOk()->assertSee('Save to draft')->assertDontSee('data-preview', false);
});
