<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class MatchResult extends Model { protected $guarded = []; protected function casts(): array { return ['confirmed_at' => 'datetime', 'validated_at' => 'datetime']; } public function match(): BelongsTo { return $this->belongsTo(MatchGame::class, 'match_id'); } }
