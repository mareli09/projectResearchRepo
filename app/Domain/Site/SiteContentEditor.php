<?php

namespace App\Domain\Site;

use App\Domain\Audit\Audit;
use App\Domain\Audit\AuditEvent;
use App\Models\ResearchRecord;
use App\Models\SiteContentVersion;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * [Not in manuscript] Landing page CMS (CLAUDE.md §8): edits go to one draft row in site_contents;
 * "Publish" saves it as a new published version (history keeps who, when and which sections);
 * "Discard changes" drops the draft; "Load default content" puts the defaults in the draft.
 * Content is plain text: tags are stripped on save and everything is escaped on output.
 */
final class SiteContentEditor
{
    /**
     * Editable fields of each tab: path => [label, max length, textarea?]; lists: [path, item fields, max items, item name].
     *
     * @return array<string, array{fields?: array<string, array{0: string, 1: int, 2?: bool}>, bools?: array<string, string>, list?: array{0: string, 1: array<string, array{0: string, 1: int, 2?: bool}>, 2: int, 3: string}}>
     */
    public static function schema(): array
    {
        $intro = fn (string $k, bool $withIntro = true) => [
            "{$k}.eyebrow" => ['Small heading above the title', 80],
            "{$k}.title" => ['Section title', 160],
        ] + ($withIntro ? ["{$k}.intro" => ['Introduction', 500, true]] : []);

        return [
            'brand' => [
                'fields' => ['site.name' => ['Site name', 80], 'site.tagline' => ['Tagline under the name', 120], 'footer.text' => ['Footer text', 200]],
                'bools' => ['theme.allowUser' => 'Let users pick their own theme color'],
            ],
            'banner' => [
                'fields' => ['banner.tag' => ['Label', 30], 'banner.link' => ['Link text (jumps to How submission works)', 60], 'banner.text' => ['Message', 300, true]],
                'bools' => ['banner.on' => 'Show the announcement bar'],
            ],
            'hero' => ['fields' => [
                'hero.eyebrow' => ['Small heading', 120], 'hero.title' => ['Headline', 160], 'hero.lead' => ['Introduction', 600, true],
                'hero.cta1' => ['Main button (opens the catalog)', 40], 'hero.cta2' => ['Second button (opens sign in)', 40],
                'hero.popular' => ['Popular searches (comma separated)', 300],
            ]],
            'stats' => ['list' => ['stats.items', ['v' => ['Number', 20], 'l' => ['Label', 120]], 4, 'Figure']],
            'about' => [
                'fields' => $intro('about', false) + ['about.body' => ['Text (leave a blank line between paragraphs)', 3000, true]],
                'list' => ['about.points', ['t' => ['Title', 80], 'd' => ['Description', 300, true]], 8, 'Feature card'],
            ],
            'featured' => ['fields' => $intro('featured')],
            'clusters' => [
                'fields' => $intro('clusters'),
                'list' => ['clusters.items', ['n' => ['Cluster name', 80], 'c' => ['Colleges', 60], 'd' => ['Description', 300, true]], 8, 'Cluster'],
            ],
            'how' => [
                'fields' => $intro('how') + ['how.note' => ['Note under the steps', 500, true]],
                'list' => ['how.steps', ['t' => ['Step', 80], 'd' => ['Description', 300, true]], 6, 'Step'],
            ],
            'sdg' => ['fields' => $intro('sdg')],
            'news' => [
                'fields' => $intro('news', false),
                'list' => ['news.items', ['d' => ['Date', 30], 'tag' => ['Label', 30], 't' => ['Headline', 160], 'b' => ['Text', 600, true]], 12, 'Item'],
            ],
            'faq' => [
                'fields' => $intro('faq', false),
                'list' => ['faq.items', ['q' => ['Question', 200], 'a' => ['Answer', 1000, true]], 20, 'Question'],
            ],
            'contact' => ['fields' => $intro('contact', false) + [
                'contact.office' => ['Office', 120], 'contact.address' => ['Address', 200], 'contact.email' => ['Email', 120],
                'contact.phone' => ['Phone', 80], 'contact.hours' => ['Office hours', 120], 'contact.help' => ['Account help (shown under Contact)', 500, true],
            ]],
        ];
    }

    public static function draftRow(): ?SiteContentVersion
    {
        return SiteContentVersion::where('status', 'draft')->latest('id')->first();
    }

    /** What the editor shows: the draft if there is one, else the published content. @return array<string, mixed> */
    public static function working(): array
    {
        $draft = self::draftRow();

        return $draft ? SiteContent::merge($draft->data) : SiteContent::published();
    }

    /** @param array<string, mixed> $data */
    private static function putDraft(array $data): void
    {
        DB::transaction(function () use ($data): void {
            $draft = self::draftRow();
            if ($draft) {
                $draft->update(['data' => $data]);
            } else {
                SiteContentVersion::create(['version' => self::nextVersion(), 'data' => $data, 'status' => 'draft']);
            }
        });
    }

    private static function nextVersion(): int
    {
        return ((int) SiteContentVersion::where('status', 'published')->max('version')) + 1;
    }

    private static function clean(mixed $v, int $max): string
    {
        // Plain text only (§8): no tags, no control characters, trimmed and limited.
        $s = strip_tags((string) $v);
        $s = (string) preg_replace('/[^\P{C}\n\t]/u', '', str_replace("\r\n", "\n", $s));

        return mb_substr(trim($s), 0, $max);
    }

    /**
     * Saves one tab of the form into the draft. List actions: add an item, remove one, move one.
     *
     * @param  array<string, mixed>  $input
     */
    public static function saveTab(string $tab, array $input): void
    {
        $schema = self::schema()[$tab] ?? null;
        $c = self::working();

        if ($tab === 'brand') {
            $color = strtolower((string) ($input['theme_color'] ?? $c['theme']['color']));
            if (! preg_match('/^#[0-9a-f]{6}$/', $color)) {
                throw ValidationException::withMessages(['theme_color' => 'Choose a color like #1e3a6b.']);
            }
            $c['theme']['color'] = $color;
        }

        if ($schema !== null) {
            foreach ($schema['fields'] ?? [] as $path => $def) {
                $value = self::clean(Arr::get($input, 'f.'.str_replace('.', '__', $path), Arr::get($c, $path)), $def[1]);
                if ($value === '' && ! str_ends_with($path, '.intro') && ! str_ends_with($path, '.eyebrow')) {
                    throw ValidationException::withMessages(['f.'.str_replace('.', '__', $path) => "{$def[0]} cannot be empty."]);
                }
                Arr::set($c, $path, $value);
            }
            foreach ($schema['bools'] ?? [] as $path => $label) {
                Arr::set($c, $path, (bool) ($input['b'][str_replace('.', '__', $path)] ?? false));
            }

            if (isset($schema['list'])) {
                [$path, $cols, $maxItems, $itemName] = $schema['list'];
                $items = [];
                foreach (is_array($input['items'] ?? null) ? $input['items'] : [] as $row) {
                    if (! is_array($row) || ! empty($row['_remove'])) {
                        continue;
                    }
                    $item = [];
                    foreach ($cols as $key => $def) {
                        $item[$key] = self::clean($row[$key] ?? '', $def[1]);
                    }
                    if ($tab === 'news') {
                        $item['on'] = ! empty($row['on']);
                    }
                    $items[] = $item;
                }
                if (! array_key_exists('items', $input)) {
                    $items = Arr::get($c, $path); // the form had no list (should not happen); keep it
                }
                if (! empty($input['move']) && preg_match('/^(\d+):(up|down)$/', (string) $input['move'], $m)) {
                    $i = (int) $m[1];
                    $j = $m[2] === 'up' ? $i - 1 : $i + 1;
                    if (isset($items[$i], $items[$j])) {
                        [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
                    }
                }
                if (! empty($input['add'])) {
                    if (count($items) >= $maxItems) {
                        throw ValidationException::withMessages(['items' => "At most {$maxItems} ".strtolower($itemName).'s.']);
                    }
                    $items[] = array_fill_keys(array_keys($cols), '') + ($tab === 'news' ? ['on' => false] : []);
                }
                Arr::set($c, $path, array_values($items));
            }
        }

        if ($tab === 'featured') {
            $mode = in_array($input['mode'] ?? '', ['manual', 'viewed', 'latest'], true) ? $input['mode'] : $c['featured']['mode'];
            $count = in_array((int) ($input['count'] ?? 0), [3, 6, 9], true) ? (int) $input['count'] : $c['featured']['count'];
            $ids = array_values(array_filter((array) $c['featured']['ids'], fn ($id) => $id !== ($input['remove_id'] ?? null)));
            if (! empty($input['add_id']) && ! in_array($input['add_id'], $ids, true)) {
                abort_unless(ResearchRecord::published()->where('public_id', $input['add_id'])->exists(), 422, 'Only published studies can be featured.');
                if (count($ids) >= 9) {
                    throw ValidationException::withMessages(['add_id' => 'At most 9 chosen studies.']);
                }
                $ids[] = (string) $input['add_id'];
            }
            $c['featured'] = array_merge($c['featured'], ['mode' => $mode, 'count' => $count, 'ids' => $ids]);
        }

        self::putDraft($c);
    }

    /** Show or hide a section, or move it up or down in the page order. */
    public static function layout(?string $toggle, ?string $move): void
    {
        $c = self::working();
        if ($toggle !== null && array_key_exists($toggle, SiteContent::SECTIONS)) {
            $c['show'][$toggle] = ! ($c['show'][$toggle] ?? true);
        }
        if ($move !== null && preg_match('/^([a-z]+):(up|down)$/', $move, $m)) {
            $order = array_values($c['order']);
            $i = array_search($m[1], $order, true);
            $j = $i === false ? false : ($m[2] === 'up' ? $i - 1 : $i + 1);
            if ($i !== false && isset($order[$j])) {
                [$order[$i], $order[$j]] = [$order[$j], $order[$i]];
            }
            $c['order'] = $order;
        }
        self::putDraft($c);
    }

    public static function discard(User $by): void
    {
        $draft = self::draftRow();
        if ($draft) {
            $draft->delete();
            Audit::record(AuditEvent::CMS, 'Landing page draft discarded', null, [], $by);
        }
    }

    public static function loadDefaults(): void
    {
        self::putDraft(SiteContent::defaults());
    }

    /** Publishes the draft as a new version. Returns the changed sections, or null when there is no draft. */
    public static function publish(User $by): ?string
    {
        $draft = self::draftRow();
        if ($draft === null) {
            return null;
        }
        $live = SiteContent::published();
        $next = SiteContent::merge($draft->data);
        $names = ['theme' => 'Theme', 'site' => 'Branding', 'footer' => 'Footer', 'banner' => 'Announcement bar', 'hero' => 'Hero',
            'order' => 'Section order', 'show' => 'Shown sections'] + SiteContent::SECTIONS;
        $changed = collect($names)->filter(fn ($n, $k) => ($live[$k] ?? null) != ($next[$k] ?? null))->values()->all();
        $summary = $changed === [] ? 'No visible changes' : implode(', ', $changed);

        DB::transaction(function () use ($draft, $by, $summary): void {
            $draft->update(['status' => 'published', 'version' => self::nextVersion(), 'summary' => mb_substr($summary, 0, 250),
                'published_by' => $by->id, 'published_at' => now()]);
        });
        SiteContent::forget();
        Audit::record(AuditEvent::CMS, "Landing page published (version {$draft->version}): {$summary}", null, ['version' => $draft->version, 'sections' => $changed], $by);

        return $summary;
    }
}
