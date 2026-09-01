<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Fixture extends BaseModel { protected function casts(): array { return ['scheduled_at' => 'datetime']; } public function round(): BelongsTo { return $this->belongsTo(Round::class); } public function homeClub(): BelongsTo { return $this->belongsTo(Club::class, 'home_club_id'); } public function awayClub(): BelongsTo { return $this->belongsTo(Club::class, 'away_club_id'); } public function matches(): HasMany { return $this->hasMany(MatchGame::class, 'fixture_id'); } }
