<?php

namespace App\Http\Controllers;

use App\Http\Requests\ResultSubmissionRequest;
use App\Models\MatchGame;
use App\Models\MatchResult;
use App\Models\MatchResultSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ResultController extends Controller
{
    public function submit(ResultSubmissionRequest $request, MatchGame $match)
    {
        $match->load('fixture'); $clubId = $request->integer('club_id');
        abort_unless(in_array($clubId, [$match->fixture->home_club_id, $match->fixture->away_club_id], true), 422, 'Club does not participate in this match.');
        abort_unless($request->user()->hasRole('SUPER_ADMIN') || $request->user()->clubs()->whereKey($clubId)->exists(), 403);
        $submission = MatchResultSubmission::create(array_merge($request->validated(), ['match_id' => $match->id, 'submitted_by' => $request->user()->id, 'submitted_at' => now()]));
        $otherSubmission = $match->submissions()->where('club_id', '!=', $clubId)->latest('submitted_at')->first();
        $match->update(['result_status' => $otherSubmission && ((int) $otherSubmission->home_goals !== (int) $submission->home_goals || (int) $otherSubmission->away_goals !== (int) $submission->away_goals) ? 'IN_CONFLICT' : 'PENDING_CONFIRMATION']);
        return response()->json($submission, 201);
    }

    public function confirm(Request $request, MatchGame $match)
    {
        $data = $request->validate(['submission_id' => ['nullable', 'integer', 'exists:match_result_submissions,id'], 'home_goals' => ['nullable', 'integer', 'min:0', 'max:255', 'required_with:away_goals'], 'away_goals' => ['nullable', 'integer', 'min:0', 'max:255', 'required_with:home_goals']]);
        $isSuperAdmin = $request->user()->hasRole('SUPER_ADMIN');
        abort_if(! $isSuperAdmin && isset($data['home_goals']), 403, 'Only a Super Admin may enter scores directly.');
        $submission = isset($data['submission_id']) ? $match->submissions()->findOrFail($data['submission_id']) : $match->submissions()->latest('submitted_at')->first();
        abort_unless($submission || isset($data['home_goals']), 422, 'A result submission or direct Super Admin scores are required.');
        if (! $isSuperAdmin) {
            $match->load('fixture');
            $clubIds = $request->user()->clubs()->pluck('clubs.id');
            abort_unless($clubIds->intersect([$match->fixture->home_club_id, $match->fixture->away_club_id])->isNotEmpty() && ! $clubIds->contains($submission->club_id), 403, 'Only the opposing club may confirm this submission.');
        }
        $scores = isset($data['home_goals']) ? ['home_goals' => $data['home_goals'], 'away_goals' => $data['away_goals']] : $submission->only(['home_goals', 'away_goals']);
        return DB::transaction(function () use ($match, $scores, $request) {
            $result = MatchResult::updateOrCreate(['match_id' => $match->id], $scores + ['status' => 'CONFIRMED', 'confirmed_at' => now(), 'confirmed_by' => $request->user()->id, 'validated_at' => null, 'validated_by' => null]);
            $match->update(['result_status' => 'CONFIRMED']);
            return $result;
        });
    }

    public function validateResult(Request $request, MatchGame $match)
    {
        $result = $match->result()->where('status', 'CONFIRMED')->firstOrFail();
        $result->update(['status' => 'VALIDATED', 'validated_at' => now(), 'validated_by' => $request->user()->id]);
        $match->update(['result_status' => 'VALIDATED', 'status' => 'FINISHED', 'ended_at' => $match->ended_at ?? now()]);
        return $result->fresh();
    }
}
