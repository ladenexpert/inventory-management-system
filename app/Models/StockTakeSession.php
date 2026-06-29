<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockTakeSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_code',
        'status',
        'reference',
        'notes',
        'row_count',
        'error_count',
        'imported_by',
        'reviewed_by',
        'posted_by',
        'closed_by',
        'imported_at',
        'reviewed_at',
        'posted_at',
        'closed_at',
        'meta',
    ];

    protected $casts = [
        'row_count' => 'integer',
        'error_count' => 'integer',
        'imported_by' => 'integer',
        'reviewed_by' => 'integer',
        'posted_by' => 'integer',
        'closed_by' => 'integer',
        'imported_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'posted_at' => 'datetime',
        'closed_at' => 'datetime',
        'meta' => 'array',
    ];

    public function rows(): HasMany
    {
        return $this->hasMany(StockTakeRow::class)->orderBy('row_number');
    }

    public function importedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function reviewedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function postedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function closedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
