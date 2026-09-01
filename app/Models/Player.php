<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Player extends BaseModel { protected function casts(): array { return ['is_active' => 'boolean']; } public function clubAssignments(): HasMany { return $this->hasMany(PlayerClub::class); } }
