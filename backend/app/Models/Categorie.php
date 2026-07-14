<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Categorie extends Model
{
    use HasFactory;

    protected $table = 'categories';

    protected $fillable = [
        'user_id',
        'nom',
        'type',
        'icone',
    ];

    // Relations
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function plannings()
    {
        return $this->hasMany(Planning::class);
    }

    // Scopes
    public function scopeSysteme($query)
    {
        return $query->whereNull('user_id');
    }

    public function scopePersonnalisees($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    // Accessible par un user donné = catégories système + les siennes
    public function scopeVisiblesPour($query, $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->whereNull('user_id')->orWhere('user_id', $userId);
        });
    }
}
