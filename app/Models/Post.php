<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Post extends Model
{
    /** @use HasFactory<\Database\Factories\PostFactory> */
    use HasFactory;

    protected $fillable = [
        'title',
        'content',
        'status',
        'user_id',
        'slug',
        'published_at'
    ];

    protected $casts = [
        'status' => 'string'
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($post) {
            if (empty($post->slug)) {
                $post->slug = $post->generateUniqueSlug();
            }
        });
    }

    protected function generateUniqueSlug(): string
    {
        $baseSlug = Str::slug($this->title);
        $slug = $baseSlug;
        $counter = 1;

        while (static::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function publish(): void
    {
        $this->update([
            'status' => 'published',
            'published_at' => now()
        ]);
    }

    public function getExcerptAttribute(): string
    {
        return Str::limit($this->content, 150);
    }

    public function generateSlug(): string
    {
        $this->slug = Str::slug($this->title);
        $this->save();
        return $this->slug;
    }
}
