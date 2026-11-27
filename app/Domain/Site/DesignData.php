<?php

namespace App\Domain\Site;

/**
 * Sample workflow data ported from the prototype. Every screen now reads real tables; this class
 * only feeds WorkflowSampleSeeder (sample submissions, requests, runs, notifications and searches
 * for the demo and evaluation copies) and holds the four step names of the stepper.
 */
final class DesignData
{
    public const STEPS = ['Submitted', 'Endorsed by adviser', 'Reviewed by coordinator', 'Approved and published'];

    /** @return list<array<string, mixed>> */
    public static function submissions(): array
    {
        return [
            ['id' => 'SUB-0142', 'title' => 'Thesis Title Similarity Screening Using TF-IDF and Cosine Similarity for Capstone Proposals', 'student' => 'Jasmine Villanueva', 'members' => ['Jasmine Villanueva', 'Carlo Mendoza', 'Hannah Cruz'], 'program' => 'BS Information Technology', 'college' => 'CEIS', 'adviser' => 'Prof. Ramon Castillo', 'type' => 'Capstone project', 'keywords' => ['tf-idf', 'cosine similarity', 'title screening', 'capstone'], 'sdg' => [4, 9],
                'abstract' => 'This capstone project develops a web-based tool that screens proposed capstone titles against previously approved studies of the college. Titles, abstracts and keywords are converted into TF-IDF vectors and compared with cosine similarity, and the five most similar studies are shown with their scores so that the panel can decide before title defense.',
                'status' => 'Endorsed', 'step' => 2, 'score' => 0.71, 'match_ids' => ['TUA-2023-1420', 'TUA-2024-1424', 'TUA-2022-1421', 'TUA-2021-1423', 'TUA-2024-1422'], 'scores' => [0.71, 0.48, 0.41, 0.36, 0.29],
                'history' => [['Submitted', 'Jasmine Villanueva', 'Nov 12, 2026 · 9:14 AM', ''], ['Endorsed', 'Prof. Ramon Castillo', 'Nov 14, 2026 · 3:40 PM', 'Complete files. Please check overlap with the 2023 capstone.']]],
            ['id' => 'SUB-0139', 'title' => 'Mobile Attendance Monitoring Application with QR Code Integration for CEIS Laboratories', 'student' => 'Enzo Navarro', 'members' => ['Enzo Navarro', 'Leah Santos'], 'program' => 'BS Information Technology', 'college' => 'CEIS', 'adviser' => 'Prof. Ramon Castillo', 'type' => 'Capstone project', 'keywords' => ['attendance', 'qr code', 'mobile', 'laboratory'], 'sdg' => [4, 9],
                'abstract' => 'A mobile application that records laboratory attendance through QR codes and syncs to a web dashboard for faculty.',
                'status' => 'Submitted', 'step' => 1, 'score' => 0.64, 'history' => [['Submitted', 'Enzo Navarro', 'Nov 17, 2026 · 10:02 AM', '']]],
            ['id' => 'SUB-0141', 'title' => 'Flood Risk Prediction for Barangays in Quezon City Using Random Forest', 'student' => 'Francine Garcia', 'members' => ['Francine Garcia', 'Rafael Lim'], 'program' => 'BS Computer Science', 'college' => 'CEIS', 'adviser' => 'Prof. Ramon Castillo', 'type' => 'Undergraduate thesis', 'keywords' => ['flood', 'prediction', 'random forest', 'barangay'], 'sdg' => [11, 13],
                'abstract' => 'The study trains a random forest model on rainfall and elevation data to predict flood risk at the barangay level.',
                'status' => 'Submitted', 'step' => 1, 'score' => 0.58, 'history' => [['Submitted', 'Francine Garcia', 'Nov 18, 2026 · 8:47 AM', '']]],
            ['id' => 'SUB-0136', 'title' => 'Inventory and Sales Monitoring System for Milk Tea Shops with SMS Notification', 'student' => 'Gabriel Ilagan', 'members' => ['Gabriel Ilagan', 'Unice Yap'], 'program' => 'BS Information Technology', 'college' => 'CEIS', 'adviser' => 'Dr. Evelyn Sarmiento', 'type' => 'Capstone project', 'keywords' => ['inventory', 'sales', 'sms', 'msme'], 'sdg' => [8, 9],
                'abstract' => 'A web system for small milk tea shops that tracks stock and daily sales and alerts owners by SMS when stock runs low.',
                'status' => 'Endorsed', 'step' => 2, 'score' => 0.52, 'history' => [['Submitted', 'Gabriel Ilagan', 'Nov 10, 2026', ''], ['Endorsed', 'Dr. Evelyn Sarmiento', 'Nov 13, 2026', '']]],
            ['id' => 'SUB-0133', 'title' => 'Structural Assessment of Pedestrian Footbridges Along Aurora Boulevard', 'student' => 'Isaac Tolentino', 'members' => ['Isaac Tolentino', 'Janelle Bautista'], 'program' => 'BS Civil Engineering', 'college' => 'CEIS', 'adviser' => 'Engr. Marites Lualhati', 'type' => 'Undergraduate thesis', 'keywords' => ['structural assessment', 'footbridge', 'pedestrian', 'civil'], 'sdg' => [9, 11],
                'abstract' => 'Visual and load assessment of five footbridges with recommendations for retrofitting.',
                'status' => 'Reviewed', 'step' => 3, 'score' => 0.31, 'history' => [['Submitted', 'Isaac Tolentino', 'Nov 3, 2026', ''], ['Endorsed', 'Engr. Marites Lualhati', 'Nov 5, 2026', ''], ['Reviewed', 'Dr. Liza Ferrer', 'Nov 9, 2026', 'Screened as original (0.31).']]],
            ['id' => 'SUB-0131', 'title' => 'Sentiment Classification of Course Evaluations Using Naive Bayes', 'student' => 'Patricia Soriano', 'members' => ['Patricia Soriano'], 'program' => 'BS Computer Science', 'college' => 'CEIS', 'adviser' => 'Dr. Evelyn Sarmiento', 'type' => 'Undergraduate thesis', 'keywords' => ['sentiment', 'classification', 'course evaluation', 'naive bayes'], 'sdg' => [4],
                'abstract' => 'Course evaluation comments are classified as positive, neutral or negative using a Naive Bayes model.',
                'status' => 'Reviewed', 'step' => 3, 'score' => 0.66, 'history' => [['Submitted', 'Patricia Soriano', 'Oct 28, 2026', ''], ['Endorsed', 'Dr. Evelyn Sarmiento', 'Oct 30, 2026', ''], ['Reviewed', 'Dr. Liza Ferrer', 'Nov 6, 2026', 'Similar to 2021 thesis but different data set and scope. Cleared.']]],
            ['id' => 'SUB-0128', 'title' => 'Online Clearance Processing System for Graduating Students', 'student' => 'Jasmine Villanueva', 'members' => ['Jasmine Villanueva', 'Miguel Espiritu', 'Zoe Ramos'], 'program' => 'BS Information Technology', 'college' => 'CEIS', 'adviser' => 'Prof. Ramon Castillo', 'type' => 'Capstone project', 'keywords' => ['clearance', 'online', 'system', 'students'], 'sdg' => [16],
                'abstract' => 'A web-based clearance process that routes signatures to offices online.',
                'status' => 'Returned', 'step' => 1, 'score' => 0.44, 'history' => [['Submitted', 'Jasmine Villanueva', 'Oct 20, 2026', ''], ['Returned', 'Prof. Ramon Castillo', 'Oct 22, 2026', 'Transfer of Ownership Agreement is unsigned. Please re-upload the signed PDF.']]],
            ['id' => 'SUB-0120', 'title' => 'Gamified Instruction in Programming Fundamentals and Its Effect on Academic Motivation', 'student' => 'Nicole Hernandez', 'members' => ['Nicole Hernandez', 'Oscar Pascual'], 'program' => 'BS Computer Science', 'college' => 'CEIS', 'adviser' => 'Dr. Evelyn Sarmiento', 'type' => 'Undergraduate thesis', 'keywords' => ['gamification', 'programming', 'motivation', 'instruction'], 'sdg' => [4],
                'abstract' => 'A quasi-experimental study on gamified activities in a first-year programming course.',
                'status' => 'Published', 'step' => 4, 'score' => 0.22, 'history' => [['Submitted', 'Nicole Hernandez', 'Oct 2, 2026', ''], ['Endorsed', 'Dr. Evelyn Sarmiento', 'Oct 4, 2026', ''], ['Reviewed', 'Dr. Liza Ferrer', 'Oct 9, 2026', ''], ['Published', 'Dr. Teresa Manalo', 'Oct 15, 2026', '']]],
        ];
    }

    /** @return array<string, mixed>|null */
    public static function submission(?string $id): ?array
    {
        return collect(self::submissions())->firstWhere('id', $id);
    }

    /**
     * Top 5 matches for a sample submission: real published records, with sample scores
     * until TF-IDF screening runs (Sprint 4).
     *
     * @param  array<string, mixed>  $sub
     * @return list<array{record: array<string, mixed>, score: float}>
     */
    public static function matches(array $sub): array
    {
        $corpus = Corpus::records()->keyBy('public_id');
        if (isset($sub['match_ids'])) {
            $records = collect((array) $sub['match_ids'])->map(fn ($id) => $corpus->get($id))->filter()->values();
            $scores = $sub['scores'];
        } else {
            /** @var list<string> $kw */
            $kw = $sub['keywords'];
            $inTitle = function (string $title) use ($kw): int {
                $t = strtolower($title);
                foreach ($kw as $k) {
                    if (str_contains($t, $k)) {
                        return 1;
                    }
                }

                return 0;
            };
            $records = Corpus::records()
                ->map(fn ($r) => $r + ['overlap' => count(array_intersect($r['keywords'], $kw)) + $inTitle($r['title'])])
                ->sortByDesc(fn ($r) => [$r['overlap'], $r['views']])
                ->take(5)->values();
            $top = (float) $sub['score'];
            $scores = [$top, round($top * .78, 2), round($top * .66, 2), round($top * .57, 2), round($top * .49, 2)];
        }

        return $records->map(fn ($r, $i) => ['record' => $r, 'score' => (float) ($scores[$i] ?? 0.2)])->all();
    }

    /** @return list<array<string, mixed>> */
    public static function notifications(string $role): array
    {
        $all = [
            ['to' => 'student', 't' => 'Nov 14, 2026 · 3:41 PM', 'subj' => 'SUB-0142 endorsed by your adviser', 'body' => 'Prof. Ramon Castillo endorsed your submission. It now goes to the research coordinator of Cluster 1.', 'read' => false],
            ['to' => 'student', 't' => 'Nov 12, 2026 · 9:15 AM', 'subj' => 'SUB-0142 received', 'body' => 'All 5 files were received. Screening result: highest match 0.71 (similar, needs review).', 'read' => true],
            ['to' => 'student', 't' => 'Oct 22, 2026 · 2:05 PM', 'subj' => 'SUB-0128 returned for revision', 'body' => 'Reason: Transfer of Ownership Agreement is unsigned. Please re-upload the signed PDF.', 'read' => true],
            ['to' => 'adviser', 't' => 'Nov 18, 2026 · 8:48 AM', 'subj' => 'New submission to endorse: SUB-0141', 'body' => 'Francine Garcia submitted "Flood Risk Prediction for Barangays in Quezon City Using Random Forest".', 'read' => false],
            ['to' => 'adviser', 't' => 'Nov 17, 2026 · 10:03 AM', 'subj' => 'New submission to endorse: SUB-0139', 'body' => 'Enzo Navarro submitted "Mobile Attendance Monitoring Application with QR Code Integration".', 'read' => false],
            ['to' => 'coordinator', 't' => 'Nov 14, 2026 · 3:41 PM', 'subj' => 'Endorsed submission flagged: SUB-0142', 'body' => 'Highest similarity 0.71 with TUA-2023-1420. Please review before it goes to the URDC.', 'read' => false],
            ['to' => 'coordinator', 't' => 'Nov 2, 2026 · 2:20 AM', 'subj' => 'Clustering RUN-06 finished', 'body' => '2 suggested theme labels are waiting for your confirmation.', 'read' => false],
            ['to' => 'urdc', 't' => 'Nov 9, 2026 · 4:12 PM', 'subj' => 'Ready for approval: SUB-0133', 'body' => 'Dr. Liza Ferrer reviewed the submission and sent it for final approval.', 'read' => false],
            ['to' => 'urdc', 't' => 'Nov 18, 2026 · 1:30 PM', 'subj' => 'Full-paper access request', 'body' => 'Jasmine Villanueva requested the full paper of TUA-2023-1420.', 'read' => false],
        ];

        return array_values(array_filter($all, fn ($n) => $n['to'] === $role));
    }

    /** @return list<array<string, string>> */
    public static function accessRequests(): array
    {
        return [
            ['id' => 'AR-031', 'who' => 'Jasmine Villanueva', 'role' => 'Student · CEIS', 'rec' => 'TUA-2023-1420', 'purpose' => 'Review of related literature for capstone', 'date' => 'Nov 18, 2026', 'status' => 'Pending'],
            ['id' => 'AR-030', 'who' => 'Carlo Mendoza', 'role' => 'Student · CEIS', 'rec' => 'TUA-2022-1421', 'purpose' => 'Comparison of methods for capstone proposal', 'date' => 'Nov 17, 2026', 'status' => 'Pending'],
            ['id' => 'AR-029', 'who' => 'Leah Santos', 'role' => 'Student · SLCN', 'rec' => 'TUA-2024-1422', 'purpose' => 'Thesis background study', 'date' => 'Nov 15, 2026', 'status' => 'Approved'],
            ['id' => 'AR-027', 'who' => 'Mika Pascual', 'role' => 'Student · IBAM', 'rec' => 'TUA-2021-1423', 'purpose' => 'Personal reading', 'date' => 'Nov 11, 2026', 'status' => 'Declined'],
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function runs(): array
    {
        return [
            ['id' => 'RUN-06', 'date' => 'Nov 2, 2026', 'n' => 1419, 'k' => 7, 'sil' => .184, 'db' => 1.87, 'by' => 'Scheduler (1st sem)', 'state' => 'Current'],
            ['id' => 'RUN-05', 'date' => 'Jun 3, 2026', 'n' => 1302, 'k' => 7, 'sil' => .176, 'db' => 1.93, 'by' => 'Scheduler (2nd sem)', 'state' => 'Archived'],
            ['id' => 'RUN-04', 'date' => 'Jan 8, 2026', 'n' => 1188, 'k' => 6, 'sil' => .169, 'db' => 2.01, 'by' => 'Dr. Teresa Manalo', 'state' => 'Archived'],
        ];
    }

    /**
     * Theme label states after RUN-06: two wait for the coordinator.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function labels(): array
    {
        $out = [];
        foreach (Corpus::THEMES as $id => $t) {
            $pending = in_array($id, [3, 4], true);
            $out[$id] = [
                'id' => $id,
                'llm' => $t['llm'],
                'label' => $pending ? $t['llm'] : $t['label'],
                'terms' => $t['terms'],
                'confirmed' => ! $pending,
                'action' => $pending ? 'pending' : ($t['llm'] === $t['label'] ? 'accepted' : 'edited'),
                'rating' => $pending ? 0 : [1 => 4, 2 => 4, 5 => 3, 6 => 4, 7 => 4][$id],
            ];
        }

        return $out;
    }

    /**
     * Searches with no or few results (Table 7 visual 13), sample until search_logs fills (Sprint 5).
     *
     * @return list<array{0: string, 1: int, 2: int, 3: string}>
     */
    public static function searchGaps(): array
    {
        return [['blockchain voting', 23, 0, 'Nov 18'], ['solar panel efficiency', 17, 1, 'Nov 17'], ['climate adaptation barangay', 14, 0, 'Nov 16'], ['augmented reality anatomy', 11, 2, 'Nov 15'], ['microplastics', 9, 0, 'Nov 12'], ['cybersecurity awareness', 8, 3, 'Nov 11']];
    }
}
