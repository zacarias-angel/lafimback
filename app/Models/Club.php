<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Club extends BaseModel { protected function casts(): array { return ['is_active' => 'boolean']; } public function users(): BelongsToMany { return $this->belongsToMany(User::class, 'club_users')->withPivot(['assigned_at', 'assigned_by']); } public function players(): HasMany { return $this->hasMany(PlayerClub::class); } }
