<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TraitementTranscription extends Model
{
    use HasFactory;

    protected $table = 'traitement_transcriptions';

    protected $fillable = [
        'uuid',
        'user_id',
        'type',
        'status',
        'file_path',
        'data',
        'message',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
