<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Round extends BaseModel { protected function casts(): array { return ['scheduled_date' => 'date']; } public function tournament(): BelongsTo { return $this->belongsTo(Tournament::class); } public function fixtures(): HasMany { return $this->hasMany(Fixture::class); } }
