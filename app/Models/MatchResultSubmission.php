<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class MatchResultSubmission extends Model { protected $guarded = []; protected function casts(): array { return ['submitted_at' => 'datetime']; } public function match(): BelongsTo { return $this->belongsTo(MatchGame::class, 'match_id'); } public function club(): BelongsTo { return $this->belongsTo(Club::class); } public function submitter(): BelongsTo { return $this->belongsTo(User::class, 'submitted_by'); } }
