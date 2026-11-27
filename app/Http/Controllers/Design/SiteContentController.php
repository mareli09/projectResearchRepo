<?php

namespace App\Http\Controllers\Design;

use App\Domain\Site\SiteContent;
use App\Http\Controllers\Controller;
use App\Models\ResearchRecord;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** [Not in manuscript] "Landing page content" editor for the URDC and System administrator (CLAUDE.md §8). */
class SiteContentController extends Controller
{
    public const TABS = ['brand' => 'Theme and branding', 'banner' => 'Announcement bar', 'hero' => 'Hero'];

    public function edit(Request $request): View
    {
        abort_unless(config('tua.features.landing_cms'), 404);
        $tab = (string) $request->query('tab', 'hero');
        if (! array_key_exists($tab, self::TABS) && ! array_key_exists($tab, SiteContent::SECTIONS)) {
            $tab = 'hero';
        }
        $c = SiteContent::published();

        return view('site.content', [
            'c' => $c,
            'tab' => $tab,
            'history' => SiteContent::history(),
            'featured' => ResearchRecord::whereIn('public_id', $c['featured']['ids'])->pluck('title', 'public_id'),
            'candidates' => ResearchRecord::published()->orderByDesc('views')->limit(25)->get(['public_id', 'title', 'views']),
            'swatches' => config('tua.theme.swatches'),
        ]);
    }
}
