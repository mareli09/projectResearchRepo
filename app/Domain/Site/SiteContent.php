<?php

namespace App\Domain\Site;

use App\Models\SiteContentVersion;
use Illuminate\Database\QueryException;

/**
 * [Not in manuscript] Landing page content (CLAUDE.md §8). Plain text only: it is escaped on output.
 * Today this returns the default content. Sprint 8 stores versions in site_contents (draft/published)
 * and the URDC edits them in "Landing page content".
 */
final class SiteContent
{
    public const SECTIONS = [
        'stats' => 'Key figures',
        'about' => 'About the repository',
        'featured' => 'Featured studies',
        'clusters' => 'Research clusters',
        'how' => 'How submission works',
        'sdg' => 'Explore by SDG',
        'news' => 'News and announcements',
        'faq' => 'Frequently asked questions',
        'contact' => 'Contact',
    ];

    /**
     * The latest published version from site_contents, laid over the defaults so a key added in a
     * later release still has a value. Lists (news items, FAQ, steps…) are replaced, not merged.
     *
     * @return array<string, mixed>
     */
    public static function published(): array
    {
        // Read once per request (the layout and the page both ask for it).
        return once(fn () => self::load());
    }

    /** @return array<string, mixed> */
    private static function load(): array
    {
        $defaults = self::defaults();
        try {
            $data = SiteContentVersion::where('status', 'published')->orderByDesc('version')->value('data');
        } catch (QueryException) {
            $data = null; // table not migrated yet
        }
        $data = is_string($data) ? json_decode($data, true) : $data;

        $out = $defaults;
        foreach ((array) $data as $key => $value) {
            $out[$key] = is_array($value) && isset($defaults[$key]) && is_array($defaults[$key]) && ! array_is_list($value)
                ? array_merge($defaults[$key], $value)
                : $value;
        }

        return $out;
    }

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return [
            'theme' => ['color' => config('tua.theme.default'), 'allowUser' => true],
            'site' => ['name' => 'TUA Research Repository', 'tagline' => 'University Research and Development Center'],
            'banner' => ['on' => true, 'tag' => 'Deadline', 'text' => 'Terminal papers for SY 2025–2026 are due at the URDC on January 30, 2027. Submit through the repository, not the Google Form.', 'link' => 'How to submit'],
            'hero' => [
                'eyebrow' => 'Trinity University of Asia · TUA Centralized Repository',
                'title' => 'Every TUA study, searchable in one place.',
                'lead' => 'Theses, capstone projects and faculty research from all six colleges, kept safe by the URDC. Read approved titles and abstracts, check whether a topic has been done before, and see where the University’s research is heading.',
                'cta1' => 'Browse the catalog',
                'cta2' => 'Submit your study',
                'popular' => 'machine learning, nursing care, MSMEs, telemedicine, flexible learning',
            ],
            'order' => array_keys(self::SECTIONS),
            'show' => array_fill_keys(array_keys(self::SECTIONS), true),
            'stats' => ['items' => [
                ['v' => '1,419', 'l' => 'theses and capstone projects on record, SY 2021–2026'],
                ['v' => '6', 'l' => 'colleges contributing research'],
                ['v' => '4', 'l' => 'URDC research clusters'],
                ['v' => '5', 'l' => 'required files checked on every submission'],
            ]],
            'about' => [
                'eyebrow' => 'About the repository',
                'title' => 'The enhanced platform of the TUA Centralized Repository',
                'body' => "The URDC already sets what must be collected, who submits it, and why. This repository keeps that protocol and replaces the Google Form channel with a system built to manage, search, screen and analyze the University’s research.\n\nOnly 159 of the 1,419 papers on record exist in digital form today. Each approved study here is stored with structured metadata, so it can be found by college, year, adviser, theme or SDG, and kept with access control, an activity log and regular backups.",
                'points' => [
                    ['t' => 'Search across six colleges', 'd' => 'Filter by college, year, research type, theme, SDG and adviser. Related studies appear on every page.'],
                    ['t' => 'Originality screening', 'd' => 'New submissions are compared with existing studies using TF-IDF and cosine similarity before they reach the URDC.'],
                    ['t' => 'Theme clustering and dashboards', 'd' => 'K-Means clustering groups studies into themes, and dashboards show trends for planning and accreditation.'],
                    ['t' => 'Secure, role-based access', 'd' => 'TUA accounts only. Guests see titles and abstracts. Full papers are released under URDC policy, and every access is logged.'],
                ],
            ],
            'featured' => ['eyebrow' => 'Featured studies', 'title' => 'Recently published research', 'intro' => 'A selection of approved studies chosen by the URDC. Open one to read its abstract.', 'mode' => 'manual', 'count' => 6, 'ids' => ['TUA-2023-1420', 'TUA-2024-1422', 'TUA-2022-1421']],
            'clusters' => [
                'eyebrow' => 'Research clusters', 'title' => 'Four clusters, six colleges',
                'intro' => 'Each URDC research cluster has a research coordinator who reviews similarity flags and confirms theme labels for its colleges.',
                'items' => [
                    ['n' => 'Business and Technology', 'c' => 'IBAM · CEIS', 'd' => 'Business, accountancy, hospitality, computing, information systems and engineering research, including capstone projects.'],
                    ['n' => 'Health: Diagnostic and Pharmaceutical', 'c' => 'CMT · CAHS', 'd' => 'Medical technology, laboratory diagnostics, pharmacy and allied health research.'],
                    ['n' => 'Health: Patient Care', 'c' => 'SLCN', 'd' => 'Nursing practice, patient care and community health research.'],
                    ['n' => 'Social Science, Humanities, and Science', 'c' => 'CASE', 'd' => 'Psychology, communication, education, the humanities and the natural sciences.'],
                ],
            ],
            'how' => [
                'eyebrow' => 'For students', 'title' => 'How submission works',
                'intro' => 'Submit once, online. Your study moves through four steps, and you get an email at each one.',
                'steps' => [
                    ['t' => 'Submit your study', 'd' => 'Fill in the form and upload the five required files. Suggested keywords and SDG tags appear for you to accept or edit.'],
                    ['t' => 'Adviser endorses', 'd' => 'Your adviser checks the submission and endorses it or returns it with comments.'],
                    ['t' => 'Coordinator reviews', 'd' => 'Your cluster’s research coordinator reviews any similarity flags from the screening.'],
                    ['t' => 'URDC approves and publishes', 'd' => 'The URDC gives final approval. Your study is indexed and appears in the catalog.'],
                ],
                'note' => 'Required files: abstract in the URDC format (Word), full paper (PDF), IMRAD version where applicable (PDF), signed Transfer of Ownership Agreement (PDF), and list of references.',
            ],
            'sdg' => ['eyebrow' => 'Sustainable Development Goals', 'title' => 'Explore by SDG', 'intro' => 'Studies are tagged with the UN Sustainable Development Goals they address.'],
            'news' => ['eyebrow' => 'Updates', 'title' => 'News and announcements', 'items' => [
                ['d' => 'Nov 26, 2026', 'tag' => 'Announcement', 't' => 'The repository is now open for terminal paper submissions', 'b' => 'Students of all six colleges can now submit their approved terminal papers online. The Google Form channel will close at the end of the semester.', 'on' => true],
                ['d' => 'Nov 20, 2026', 'tag' => 'Guide', 't' => 'New: suggested keywords and SDG tags', 'b' => 'When you submit, the system suggests keywords, SDG tags and a research type from your title and abstract. Nothing is added until you accept it.', 'on' => true],
                ['d' => 'Nov 12, 2026', 'tag' => 'Event', 't' => 'URDC orientation for research coordinators', 'b' => 'Coordinators of the four clusters will be trained on similarity review and theme labeling. Schedule to follow.', 'on' => true],
            ]],
            'faq' => ['eyebrow' => 'Help', 'title' => 'Frequently asked questions', 'items' => [
                ['q' => 'Who can create an account?', 'a' => 'Students and faculty with a @tua.edu.ph email address. Advisers, coordinators and URDC staff are given their roles by the system administrator.'],
                ['q' => 'Can I read full papers?', 'a' => 'Guests can read titles and abstracts of approved studies. Signed-in users can request a full paper, and the URDC approves or declines the request under its policy.'],
                ['q' => 'Will the similarity check reject my study?', 'a' => 'No. The check only flags studies that look similar to existing ones. Your research coordinator decides, and any return comes with a reason you can act on.'],
                ['q' => 'I lost access to my TUA email. How do I recover my account?', 'a' => 'Use Forgot password and choose your recovery email or mobile number to get a 6-digit code. Add these under Account and recovery while you still have access. If you can’t use any of them, the URDC or the system administrator can confirm your identity and send a reset link to a new contact.'],
                ['q' => 'What happens to my personal data?', 'a' => 'It is processed under the Data Privacy Act of 2012 (RA 10173) for repository use only. Only your title, abstract and keywords are sent to the suggestion service.'],
            ]],
            'contact' => [
                'eyebrow' => 'Contact', 'title' => 'University Research and Development Center',
                'office' => 'URDC Office, Trinity University of Asia', 'address' => '275 E. Rodriguez Sr. Ave., Quezon City',
                'email' => 'urdc@tua.edu.ph (sample)', 'phone' => 'To be supplied by the URDC', 'hours' => 'Monday to Friday, 8:00 AM to 5:00 PM',
                'help' => 'Locked out? Use Forgot password to get a code at your TUA email, recovery email or mobile. If none work, bring a school or government ID to the URDC office.',
            ],
            'footer' => ['text' => '© 2026 Trinity University of Asia · University Research and Development Center'],
        ];
    }

    /** @return list<array{t: string, who: string, what: string}> publishing history, newest first */
    public static function history(): array
    {
        return SiteContentVersion::with('publisher:id,email')
            ->where('status', 'published')->orderByDesc('version')->limit(8)->get()
            ->map(fn (SiteContentVersion $v) => [
                't' => $v->published_at?->format('M j, Y · g:i A') ?? '—',
                'who' => $v->publisher->email ?? 'system',
                'what' => $v->summary ?? 'Version '.$v->version,
            ])->values()->all();
    }
}
