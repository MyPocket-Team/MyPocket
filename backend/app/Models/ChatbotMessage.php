<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatbotMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'role',
        'contenu',
    ];

    // Relations
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeDeUtilisateur($query)
    {
        return $query->where('role', 'user');
    }

    public function scopeDeAssistant($query)
    {
        return $query->where('role', 'assistant');
    }
}
