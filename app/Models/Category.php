<?php
namespace App\Models;
class Category extends BaseModel { protected function casts(): array { return ['is_active' => 'boolean']; } }
