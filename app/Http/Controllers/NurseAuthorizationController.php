<?php

namespace App\Http\Controllers;

use App\Models\FollowupAuthorization;
use App\Models\NurseAuthorization;
use App\Models\User;
use App\Notifications\NurseOrderingChanged;
use App\Support\FollowupAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class NurseAuthorizationController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(FollowupAccess::doctor($request->user()), 403);
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'ordering' => ['nullable', 'in:on,off,unusable'], 'grants' => ['nullable', 'in:with,without']]);
        $active = NurseAuthorization::active()->with(['doctor'])->get()->keyBy('nurse_id');
        // Eligible nurses, plus anyone still holding an active authorization so a doctor can always see and turn it off.
        $nurses = User::whereIn('id', $active->keys())->orWhere(fn ($query) => $query->role('Nurse')->where('is_active', true))->orderBy('name')->get()
            ->filter(fn (User $nurse) => $active->has($nurse->id) || (FollowupAccess::nurse($nurse) && ! $nurse->is($request->user())))->values();

        $olderGrants = FollowupAuthorization::whereNull('used_at')->whereNull('revoked_at')->whereIn('nurse_id', $nurses->pluck('id'))
            ->selectRaw('nurse_id, count(*) as total')->groupBy('nurse_id')->pluck('total', 'nurse_id');

        $terms = preg_split('/\s+/u', mb_strtolower(trim($filters['search'] ?? '')), -1, PREG_SPLIT_NO_EMPTY);
        $nurses = $nurses->filter(function (User $nurse) use ($filters, $terms, $active, $olderGrants, $request) {
            $entry = $active->get($nurse->id);
            $usable = $entry && $entry->usable() && FollowupAccess::nurse($nurse) && ! $nurse->is($request->user());
            if (($filters['ordering'] ?? '') === 'on' && ! $entry) {
                return false;
            }
            if (($filters['ordering'] ?? '') === 'off' && $entry) {
                return false;
            }
            if (($filters['ordering'] ?? '') === 'unusable' && (! $entry || $usable)) {
                return false;
            }
            if (($filters['grants'] ?? '') === 'with' && ! $olderGrants->get($nurse->id)) {
                return false;
            }
            if (($filters['grants'] ?? '') === 'without' && $olderGrants->get($nurse->id)) {
                return false;
            }
            $text = mb_strtolower($nurse->name.' '.$nurse->email.' '.$entry?->doctor?->name.' '.$entry?->allowedTestsLabel());

            return collect($terms)->every(fn ($term) => str_contains($text, $term));
        })->values();

        return view('nurse-authorizations.index', compact('nurses', 'active', 'olderGrants'));
    }

    // One nurse's ordering access: switch, permitted tests, change history and older visit-specific grants.
    public function show(Request $request, User $nurse)
    {
        abort_unless(FollowupAccess::doctor($request->user()), 403);
        $eligible = FollowupAccess::nurse($nurse) && ! $nurse->is($request->user());
        $history = NurseAuthorization::with(['doctor', 'revoker'])->where('nurse_id', $nurse->id)->latest('id')->limit(20)->get();
        $olderGrants = FollowupAuthorization::with(['doctor', 'visit.patient'])->where('nurse_id', $nurse->id)->whereNull('used_at')->whereNull('revoked_at')->latest('id')->get();
        abort_unless($eligible || $history->isNotEmpty() || $olderGrants->isNotEmpty(), 404);
        $current = $history->first(fn ($entry) => ! $entry->revoked_at);

        return view('nurse-authorizations.show', compact('nurse', 'eligible', 'history', 'current', 'olderGrants'));
    }

    // Withdraws an unused visit-specific grant created before standing authorization replaced it.
    public function withdrawVisitGrant(Request $request, User $nurse, string $grant)
    {
        abort_unless(FollowupAccess::doctor($request->user()), 403);
        DB::transaction(function () use ($request, $nurse, $grant) {
            $entry = FollowupAuthorization::where('nurse_id', $nurse->id)->lockForUpdate()->findOrFail($grant);
            if ($entry->used_at || $entry->revoked_at) {
                throw ValidationException::withMessages(['nurse' => 'This visit authorization was already used or withdrawn. Reload the page.']);
            }
            $entry->revoked_at = now();
            $entry->revoked_by = $request->user()->id;
            $entry->save();
            $entry->clearNurseAlert();
            activity('followups')->causedBy($request->user())->performedOn($entry)->withProperties(['visit_id' => $entry->visit_id])->log('followup.revoked');
        });

        return redirect()->route('nurse-authorizations.show', $nurse)->with('status', 'Older visit authorization withdrawn.');
    }

    // Turns a nurse's ordering on, or replaces their permitted tests with a new version.
    public function store(Request $request, User $nurse)
    {
        abort_unless(FollowupAccess::doctor($request->user()), 403);
        $data = $request->validate([
            'test_scope' => ['required', Rule::in(['ANY', 'SELECTED'])],
            'allowed_tests' => ['required_if:test_scope,SELECTED', 'prohibited_unless:test_scope,SELECTED', 'array', 'min:1'],
            'allowed_tests.*' => ['string', 'distinct', Rule::in(NurseAuthorization::catalog())],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'doctor_id' => ['prohibited'], 'nurse_id' => ['prohibited'], 'allow_any_test' => ['prohibited'],
        ]);
        if ($nurse->is($request->user()) || ! FollowupAccess::nurse($nurse)) {
            throw ValidationException::withMessages(['nurse' => 'Only another active nurse with follow-up access can be authorized.']);
        }
        $changed = DB::transaction(function () use ($request, $nurse, $data) {
            // Lock the nurse's row so two doctors cannot create two active authorizations at once.
            User::whereKey($nurse->id)->lockForUpdate()->first();
            $previous = NurseAuthorization::active()->where('nurse_id', $nurse->id)->get();
            foreach ($previous as $old) {
                $this->close($old, $request->user());
            }
            $any = $data['test_scope'] === 'ANY';
            $entry = new NurseAuthorization;
            $entry->nurse_id = $nurse->id;
            $entry->doctor_id = $request->user()->id;
            $entry->allow_any_test = $any;
            $entry->allowed_tests = $any ? null : array_values(array_intersect(NurseAuthorization::catalog(), $data['allowed_tests']));
            $entry->instructions = $data['instructions'] ?? null;
            $entry->save();
            $state = $previous->isEmpty() ? 'on' : 'changed';
            activity('followups')->causedBy($request->user())->performedOn($entry)
                ->withProperties(['nurse_id' => $nurse->id, 'state' => $state, 'allow_any_test' => $any, 'allowed_test_count' => $any ? null : count($entry->allowed_tests), 'replaced_ids' => $previous->pluck('id')->all()])
                ->log('nurse_authorization.'.$state);
            $nurse->notify(new NurseOrderingChanged($entry, $state, $request->user()->id));

            return $state === 'changed';
        });

        return redirect()->route('nurse-authorizations.show', $nurse)->with('status', $changed
            ? "Permitted tests updated for {$nurse->name}. They have been notified."
            : "Laboratory ordering turned on for {$nurse->name}. They have been notified.");
    }

    public function destroy(Request $request, User $nurse)
    {
        abort_unless(FollowupAccess::doctor($request->user()), 403);
        DB::transaction(function () use ($request, $nurse) {
            User::whereKey($nurse->id)->lockForUpdate()->first();
            $active = NurseAuthorization::active()->where('nurse_id', $nurse->id)->get();
            if ($active->isEmpty()) {
                throw ValidationException::withMessages(['nurse' => 'Laboratory ordering is already off for this nurse. Reload the page.']);
            }
            foreach ($active as $entry) {
                $this->close($entry, $request->user());
                activity('followups')->causedBy($request->user())->performedOn($entry)->withProperties(['nurse_id' => $nurse->id, 'state' => 'off'])->log('nurse_authorization.off');
            }
            $nurse->notify(new NurseOrderingChanged($active->last(), 'off', $request->user()->id));
        });

        return redirect()->route('nurse-authorizations.show', $nurse)->with('status', "Laboratory ordering turned off for {$nurse->name}. They have been notified.");
    }

    private function close(NurseAuthorization $entry, User $doctor): void
    {
        $entry->revoked_at = now();
        $entry->revoked_by = $doctor->id;
        $entry->save();
    }
}
