<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Club;
use App\Models\MatchGame;
use App\Models\Player;
use App\Models\PlayerClub;
use App\Models\Tournament;
use Illuminate\Http\Request;

class ClubAdminController extends Controller
{
    public function dashboard(Request $request)
    {
        $clubIds = $request->user()->clubs()->pluck('clubs.id');
        $matches = MatchGame::query()->with(['category', 'fixture.homeClub', 'fixture.awayClub'])
            ->whereHas('fixture', fn ($query) => $query->whereIn('home_club_id', $clubIds)->orWhereIn('away_club_id', $clubIds))
            ->whereNotIn('status', ['CANCELLED'])
            ->orderByDesc('id')->get();

        return response()->json([
            'clubs' => Club::query()->whereIn('id', $clubIds)->orderBy('name')->get(['id', 'name', 'description', 'logo_url']),
            'categories' => Category::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
            'tournaments' => Tournament::query()->where('status', 'ACTIVE')->orderByDesc('id')->get(['id', 'name', 'season']),
            'matches' => $matches->map(fn (MatchGame $match) => [
                'id' => $match->id, 'category' => $match->category->name, 'scheduledAt' => $match->fixture->scheduled_at?->toIso8601String(),
                'homeClub' => $match->fixture->homeClub->name, 'awayClub' => $match->fixture->awayClub->name,
                'homeClubId' => $match->fixture->home_club_id, 'awayClubId' => $match->fixture->away_club_id,
                'status' => $match->status, 'resultStatus' => $match->result_status,
            ]),
        ]);
    }

    public function updateClub(Request $request, Club $club)
    {
        $data = $request->validate(['description' => ['nullable', 'string'], 'logo_url' => ['nullable', 'url', 'max:500']]);
        $club->update($data);
        return $club->fresh();
    }

    public function storePlayer(Request $request, Club $club)
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:80'], 'last_name' => ['required', 'string', 'max:80'],
            'position' => ['nullable', 'string', 'max:20'], 'shirt_number' => ['nullable', 'integer', 'min:0', 'max:255'],
            'category_id' => ['required', 'exists:categories,id'], 'tournament_id' => ['required', 'exists:tournaments,id'],
        ]);
        $player = Player::create(collect($data)->only(['first_name', 'last_name', 'position', 'shirt_number'])->all());
        PlayerClub::create(['player_id' => $player->id, 'club_id' => $club->id, 'category_id' => $data['category_id'], 'tournament_id' => $data['tournament_id'], 'joined_at' => today()]);
        return response()->json($player, 201);
    }
}
