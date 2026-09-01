<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class PlayerClub extends Model { protected $guarded = []; protected function casts(): array { return ['joined_at' => 'date', 'left_at' => 'date']; } public function player(): BelongsTo { return $this->belongsTo(Player::class); } public function club(): BelongsTo { return $this->belongsTo(Club::class); } }
