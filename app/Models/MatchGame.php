<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
class MatchGame extends BaseModel { protected $table = 'matches'; protected function casts(): array { return ['started_at' => 'datetime', 'ended_at' => 'datetime']; } public function fixture(): BelongsTo { return $this->belongsTo(Fixture::class); } public function category(): BelongsTo { return $this->belongsTo(Category::class); } public function result(): HasOne { return $this->hasOne(MatchResult::class, 'match_id'); } public function submissions(): HasMany { return $this->hasMany(MatchResultSubmission::class, 'match_id'); } }
