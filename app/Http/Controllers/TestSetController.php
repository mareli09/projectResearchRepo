<?php

namespace App\Http\Controllers;

use App\Domain\Audit\Audit;
use App\Domain\Audit\AuditEvent;
use App\Domain\Screening\TestSet;
use App\Models\TestPair;
use App\Services\AnalyticsClient\AnalyticsClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Labeled screening test set (CLAUDE.md §7 M5, §17). The URDC builds the pairs; coordinators
 * label them, without the score; the AI performance page measures screening against the majority.
 */
class TestSetController extends Controller
{
    public function index(): View
    {
        $pairs = TestPair::with(['labels', 'record:id,public_id,title'])->orderByDesc('id')->paginate(25);

        return view('urdc.test-set', [
            'stats' => TestSet::stats(),
            'pairs' => $pairs,
            'target' => TestSet::TARGET,
        ]);
    }

    public function sample(Request $request, AnalyticsClient $analytics): RedirectResponse
    {
        $n = (int) $request->validate(['n' => ['required', 'integer', 'min:2', 'max:200']])['n'];
        try {
            $added = TestSet::sample($n, $request->user(), $analytics);
        } catch (RuntimeException $e) {
            return back()->withErrors(['n' => $e->getMessage()]);
        }

        return back()->with('status', "{$added} pairs were added for the coordinators to label.");
    }

    public function import(Request $request, AnalyticsClient $analytics): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:2048']], ['file.mimes' => 'Upload a CSV file.']);
        $handle = fopen((string) $request->file('file')?->getRealPath(), 'r');
        $rows = [];
        while ($handle !== false && ($row = fgetcsv($handle)) !== false) {
            if ($row === [null]) {
                continue;
            }
            $rows[] = array_map(fn ($v) => $v === null ? '' : (string) $v, $row);
        }
        if ($handle !== false) {
            fclose($handle);
        }
        if ($rows !== [] && strtolower(trim(ltrim((string) $rows[0][0], "\xEF\xBB\xBF"))) === 'title') {
            array_shift($rows);
        }
        if (count($rows) > 300) {
            return back()->withErrors(['file' => 'At most 300 rows per file.']);
        }

        $result = TestSet::import($rows, $request->user(), $analytics);

        return back()->with('status', "{$result['added']} pairs were imported.")->with('importErrors', array_slice($result['errors'], 0, 20));
    }

    public function score(AnalyticsClient $analytics): RedirectResponse
    {
        $n = TestSet::scoreMissing($analytics);

        return back()->with('status', $n ? "{$n} pairs were scored." : 'No pair could be scored. Check that the analytics service is running.');
    }

    public function destroy(Request $request, TestPair $pair): RedirectResponse
    {
        $id = $pair->public_id;
        $labels = $pair->labels()->count();
        $pair->delete();
        Audit::record(AuditEvent::IMPORT, "Test pair {$id} removed", null, ['labels' => $labels], $request->user());

        return back()->with('status', "{$id} was removed.");
    }

    public function export(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Pair', 'Source', 'Study title', 'Record', 'Record title', 'Score', 'Duplicate labels', 'Distinct labels', 'Reference label']);
            foreach (TestPair::with(['labels', 'record:id,public_id,title'])->orderBy('id')->get() as $p) {
                fputcsv($out, [
                    $p->public_id, $p->source, self::safe($p->left_title), $p->record->public_id ?? '', self::safe((string) ($p->record->title ?? '')),
                    $p->score === null ? '' : number_format((float) $p->score, 3),
                    $p->labels->where('label', 'duplicate')->count(), $p->labels->where('label', 'distinct')->count(),
                    TestSet::majority($p->labels) ?? 'undecided',
                ]);
            }
            fclose($out);
        }, 'labeled-test-set-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Coordinators: label the next pair. The score is not shown, so it cannot sway the label. */
    public function labelPage(Request $request): View
    {
        $user = $request->user();

        return view('coordinator.test-set', [
            'pair' => TestSet::nextFor($user),
            'done' => TestSet::labeledBy($user),
            'waiting' => TestSet::waitingFor($user),
            'stats' => TestSet::stats(),
        ]);
    }

    public function label(Request $request, TestPair $pair): RedirectResponse
    {
        $label = (string) $request->validate(['label' => ['required', 'in:duplicate,distinct']])['label'];
        TestSet::label($pair, $request->user(), $label);

        return redirect()->route('coordinator.testset')->with('status', "{$pair->public_id} labeled {$label}. Here is the next pair.");
    }

    private static function safe(string $v): string
    {
        return $v !== '' && in_array($v[0], ['=', '+', '-', '@'], true) ? "'".$v : $v;
    }
}
