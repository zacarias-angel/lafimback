<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class News extends BaseModel { protected function casts(): array { return ['published_at' => 'datetime']; } public function author(): BelongsTo { return $this->belongsTo(User::class, 'author_id'); } public function relations(): HasMany { return $this->hasMany(NewsRelation::class); } }
