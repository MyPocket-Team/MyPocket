<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'planning_id',
        'planned_transaction_id',
        'milestone',
        'type',
        'titre',
        'motif',
        'lue',
        'date_envoi',
    ];

    protected function casts(): array
    {
        return [
            'lue' => 'boolean',
            'date_envoi' => 'datetime',
        ];
    }

    // Relations
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function planning()
    {
        return $this->belongsTo(Planning::class);
    }

    public function plannedTransaction()
    {
        return $this->belongsTo(PlannedTransaction::class);
    }

    // Scopes
    public function scopeNonLues($query)
    {
        return $query->where('lue', false);
    }

    public function scopeRappelsPlanning($query)
    {
        return $query->where('type', 'rappel_planning');
    }

    public function scopeAlertesSeuilBas($query)
    {
        return $query->where('type', 'seuil_bas');
    }

    // Helpers
    public function marquerCommeLue(): void
    {
        $this->update(['lue' => true]);
    }
}
