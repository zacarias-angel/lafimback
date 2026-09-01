<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\MatchGame;
use App\Models\News;
use App\Models\Player;
use App\Models\StandingBaseline;
use App\Models\Tournament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublicController extends Controller
{
    public function matches(Request $request, string $scope)
    {
        abort_unless(in_array($scope, ['upcoming', 'today', 'completed'], true), 404);
        $query = MatchGame::with(['category', 'result', 'fixture.round.tournament', 'fixture.homeClub', 'fixture.awayClub']);
        if ($scope === 'completed') $query->where('status', 'FINISHED');
        elseif ($scope === 'today') $query->whereHas('fixture', fn ($q) => $q->whereDate('scheduled_at', today()));
        else $query->whereHas('fixture', fn ($q) => $q->where('scheduled_at', '>=', now()))->where('status', 'SCHEDULED');
        return $query->orderByDesc('id')->paginate($request->integer('per_page', 20));
    }

    public function standings(Request $request, Tournament $tournament, $category)
    {
        return response()->json($this->standingsData($tournament, $category));
    }

    public function currentStandings(Request $request, $category)
    {
        return response()->json($this->standingsData(Tournament::query()->where('status', 'ACTIVE')->firstOrFail(), $category));
    }

    private function standingsData(Tournament $tournament, $category): array
    {
        $categoryId = is_numeric($category) ? $category : $tournament->categories()->where(fn ($query) => $query->where('slug', $category)->orWhere('name', $category))->value('categories.id');
        abort_unless($categoryId && $tournament->categories()->whereKey($categoryId)->exists(), 404);
        $points = $tournament->only(['points_win', 'points_draw', 'points_loss']);
        $table = [];
        $snapshotRounds = [];
        foreach (StandingBaseline::query()->where('tournament_id', $tournament->id)->where('category_id', $categoryId)->get() as $baseline) {
            $table[$baseline->club_id] = $baseline->only(['club_id', 'played', 'won', 'drawn', 'lost', 'goals_for', 'goals_against', 'points']);
            $snapshotRounds[$baseline->club_id] = $baseline->snapshot_round_number;
        }
        $rows = DB::table('match_results as mr')->join('matches as m', 'm.id', '=', 'mr.match_id')->join('fixtures as f', 'f.id', '=', 'm.fixture_id')->join('rounds as r', 'r.id', '=', 'f.round_id')->where('r.tournament_id', $tournament->id)->where('m.category_id', $categoryId)->whereIn('mr.status', ['CONFIRMED', 'VALIDATED'])->select('f.home_club_id', 'f.away_club_id', 'mr.home_goals', 'mr.away_goals', 'r.number as round_number')->get();
        foreach ($rows as $row) foreach ([['id' => $row->home_club_id, 'for' => $row->home_goals, 'against' => $row->away_goals], ['id' => $row->away_club_id, 'for' => $row->away_goals, 'against' => $row->home_goals]] as $side) { $id = $side['id']; if (isset($snapshotRounds[$id]) && $row->round_number <= $snapshotRounds[$id]) continue; $table[$id] ??= ['club_id' => $id, 'played' => 0, 'won' => 0, 'drawn' => 0, 'lost' => 0, 'goals_for' => 0, 'goals_against' => 0, 'points' => 0]; $table[$id]['played']++; $table[$id]['goals_for'] += $side['for']; $table[$id]['goals_against'] += $side['against']; if ($side['for'] > $side['against']) { $table[$id]['won']++; $table[$id]['points'] += $points['points_win']; } elseif ($side['for'] === $side['against']) { $table[$id]['drawn']++; $table[$id]['points'] += $points['points_draw']; } else { $table[$id]['lost']++; $table[$id]['points'] += $points['points_loss']; } }
        $clubs = Club::whereIn('id', array_keys($table))->pluck('name', 'id');
        $table = array_values(array_map(fn ($row) => $row + ['club_name' => $clubs[$row['club_id']], 'goal_difference' => $row['goals_for'] - $row['goals_against']], $table));
        usort($table, fn ($a, $b) => [$b['points'], $b['goal_difference'], $b['goals_for']] <=> [$a['points'], $a['goal_difference'], $a['goals_for']]);
        return ['tournament_id' => $tournament->id, 'category_id' => $categoryId, 'standings' => $table];
    }

    public function clubs(Request $request) { return Club::query()->where('is_active', true)->orderBy('name')->paginate($request->integer('per_page', 20)); }
    public function club(Club $club) { return $club->load(['players.player']); }
    public function players(Request $request) { return Player::query()->where('is_active', true)->with('clubAssignments')->orderBy('last_name')->paginate($request->integer('per_page', 20)); }
    public function news(Request $request) { return News::query()->where('status', 'PUBLISHED')->where('published_at', '<=', now())->with('author:id,name')->latest('published_at')->paginate($request->integer('per_page', 20)); }

    public function spanishMatches(Request $request, string $scope)
    {
        return $this->matches($request, ['proximos' => 'upcoming', 'hoy' => 'today', 'jugados' => 'completed'][$scope])->through(fn (MatchGame $match) => ['id' => $match->id, 'local' => $match->fixture->homeClub->name, 'visitante' => $match->fixture->awayClub->name, 'categoria' => $match->category->name, 'fecha' => $match->fixture->scheduled_at?->toDateString(), 'hora' => $match->fixture->scheduled_at?->format('H:i'), 'estado' => $match->status, 'goles_local' => $match->result?->home_goals, 'goles_visitante' => $match->result?->away_goals, 'cancha' => $match->fixture->venue]);
    }

    public function spanishClubs(Request $request) { return $this->clubs($request)->through(fn (Club $club) => ['id' => $club->id, 'nombre' => $club->name, 'descripcion' => $club->description, 'escudo_url' => $club->logo_url]); }
    public function spanishClub(Club $club) { return ['id' => $club->id, 'nombre' => $club->name, 'descripcion' => $club->description, 'escudo_url' => $club->logo_url]; }
    public function spanishPlayers(Request $request) { return $this->players($request)->through(fn (Player $player) => ['id' => $player->id, 'nombre' => $player->first_name, 'apellido' => $player->last_name, 'posicion' => $player->position, 'numero' => $player->shirt_number]); }
    public function spanishNews(Request $request) { return $this->news($request)->through(fn (News $news) => ['id' => $news->id, 'titulo' => $news->title, 'resumen' => $news->summary, 'contenido' => $news->body, 'tipo' => $news->type, 'publicada_en' => $news->published_at?->toIso8601String()]); }
    public function spanishStandings(Request $request, $category) { $data = $this->standingsData(Tournament::query()->where('status', 'ACTIVE')->firstOrFail(), $category); return response()->json(['data' => array_map(fn ($row, $index) => ['posicion' => $index + 1, 'club' => $row['club_name'], 'puntos' => $row['points'], 'pj' => $row['played'], 'pg' => $row['won'], 'pe' => $row['drawn'], 'pp' => $row['lost'], 'gf' => $row['goals_for'], 'gc' => $row['goals_against'], 'dg' => $row['goal_difference']], $data['standings'], array_keys($data['standings']))]); }
}
