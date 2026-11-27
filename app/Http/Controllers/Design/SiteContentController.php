<?php

namespace App\Http\Controllers\Design;

use App\Domain\Site\SiteContent;
use App\Domain\Site\SiteContentEditor;
use App\Http\Controllers\Controller;
use App\Models\ResearchRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** [Not in manuscript] "Landing page content" editor for the URDC and System administrator (CLAUDE.md §8). */
class SiteContentController extends Controller
{
    public const TABS = ['brand' => 'Theme and branding', 'banner' => 'Announcement bar', 'hero' => 'Hero'];

    public function edit(Request $request): View
    {
        $tab = $this->tab((string) $request->query('tab', 'hero'));
        $c = SiteContentEditor::working();
        $draft = SiteContentEditor::draftRow();

        return view('site.content', [
            'c' => $c,
            'tab' => $tab,
            'schema' => SiteContentEditor::schema()[$tab] ?? [],
            'draft' => $draft,
            'history' => SiteContent::history(),
            'featured' => ResearchRecord::whereIn('public_id', $c['featured']['ids'])->pluck('title', 'public_id'),
            'candidates' => ResearchRecord::published()->whereNotIn('public_id', $c['featured']['ids'])->orderByDesc('views')->limit(25)->get(['public_id', 'title', 'views']),
            'swatches' => config('tua.theme.swatches'),
        ]);
    }

    public function save(Request $request, string $tab): RedirectResponse
    {
        $tab = $this->tab($tab);
        SiteContentEditor::saveTab($tab, $request->all());

        return redirect()->route('site.content', ['tab' => $tab])->with('status', 'Saved to the draft. Preview it, then publish when ready.');
    }

    public function layout(Request $request): RedirectResponse
    {
        SiteContentEditor::layout($request->input('toggle'), $request->input('move'));

        return redirect()->route('site.content', ['tab' => $this->tab((string) $request->input('tab', 'hero'))])->with('status', 'Section layout saved to the draft.');
    }

    public function publish(Request $request): RedirectResponse
    {
        $summary = SiteContentEditor::publish($request->user());

        return back()->with('status', $summary === null ? 'There are no draft changes to publish.' : "Published. Changed: {$summary}.");
    }

    public function discard(Request $request): RedirectResponse
    {
        SiteContentEditor::discard($request->user());

        return back()->with('status', 'Draft discarded. The editor shows the published page again.');
    }

    public function defaults(): RedirectResponse
    {
        SiteContentEditor::loadDefaults();

        return back()->with('status', 'The default content is in the draft. Publish it to make it live, or discard it.');
    }

    private function tab(string $tab): string
    {
        abort_unless(config('tua.features.landing_cms'), 404);

        return array_key_exists($tab, self::TABS) || array_key_exists($tab, SiteContent::SECTIONS) ? $tab : 'hero';
    }
}
