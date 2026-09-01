<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Tournament extends BaseModel { protected function casts(): array { return ['tiebreak_rules' => 'array']; } public function categories(): BelongsToMany { return $this->belongsToMany(Category::class, 'tournament_categories'); } public function rounds(): HasMany { return $this->hasMany(Round::class); } }
