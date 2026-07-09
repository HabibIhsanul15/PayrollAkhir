<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends Model
{
    protected $table = 'journal_entries';

    protected $fillable = [
        'journal_number',
        'journal_type',
        'transaction_date',
        'reference_type',
        'reference_id',
        'description',
        'status',
    ];

    protected $casts = [
        'transaction_date' => 'date',
    ];

    protected $attributes = [
        'status' => 'posted',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(JournalItem::class, 'journal_entry_id');
    }
}
