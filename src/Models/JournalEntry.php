<?php

namespace Ongudidan\PaymentsEngine\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends BaseModel
{
    protected $baseTable = 'journal_entries';

    protected $fillable = [
        'date',
        'reference',
        'description',
        'entry_type',
        'company_id',
        'branch_id',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(JournalItem::class, 'journal_entry_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(static::modelClass('user'), 'created_by');
    }
}
