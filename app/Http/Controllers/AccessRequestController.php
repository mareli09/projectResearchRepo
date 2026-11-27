<?php

namespace App\Http\Controllers;

use App\Domain\Repository\AccessRequests;
use App\Models\AccessRequest;
use App\Models\ResearchRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/** Full-paper requests (CLAUDE.md §7 M4): students ask, the URDC decides. */
class AccessRequestController extends Controller
{
    public function store(Request $request, string $publicId): RedirectResponse
    {
        $record = ResearchRecord::published()->where('public_id', $publicId)->firstOrFail();
        $data = $request->validate(['purpose' => ['required', 'string', 'min:15', 'max:1000']],
            ['purpose.required' => 'Say what you need the full paper for.', 'purpose.min' => 'Describe the purpose in at least 15 characters.']);

        try {
            $req = AccessRequests::request($request->user(), $record, $data['purpose']);
        } catch (RuntimeException $e) {
            return back()->withErrors(['purpose' => $e->getMessage()]);
        }

        return back()->with('status', "Request {$req->public_id} sent. The URDC will email you its decision.");
    }

    public function index(Request $request): View
    {
        $status = in_array($request->query('status'), ['pending', 'approved', 'declined'], true) ? $request->query('status') : null;

        return view('urdc.requests', [
            'requests' => AccessRequest::with('user.roles', 'user.academicUnit', 'record:id,public_id,title')
                ->when($status, fn ($q) => $q->where('status', $status))
                ->orderByRaw("status = 'pending' DESC")->latest('id')->paginate(20)->withQueryString(),
            'status' => $status,
            'pending' => AccessRequest::where('status', 'pending')->count(),
            'defaultExpiry' => now()->addDays(AccessRequests::DEFAULT_DAYS)->toDateString(),
        ]);
    }

    public function decide(Request $request, AccessRequest $accessRequest): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'decline'])],
            'expires_at' => ['exclude_unless:decision,approve', 'required', 'date', 'after:today', 'before:'.now()->addYear()->toDateString()],
            'reason' => ['exclude_unless:decision,decline', 'required', 'string', 'min:5', 'max:1000'],
        ], ['reason.required' => 'Give the reason for declining. The student sees it.', 'expires_at.after' => 'The expiry date must be after today.']);

        try {
            if ($data['decision'] === 'approve') {
                AccessRequests::approve($accessRequest, $request->user(), Carbon::parse($data['expires_at']));
            } else {
                AccessRequests::decline($accessRequest, $request->user(), $data['reason']);
            }
        } catch (RuntimeException $e) {
            return back()->withErrors(['decision' => $e->getMessage()]);
        }

        return back()->with('status', "{$accessRequest->public_id} ".($data['decision'] === 'approve' ? 'approved' : 'declined').'. The student was emailed.');
    }
}
