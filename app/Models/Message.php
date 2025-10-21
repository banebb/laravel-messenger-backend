<?php

namespace App\Models;

use DateTime;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $fillable = [
        'sender_id',
        'receiver_id',
        'room_id',
        'content'
    ];

    public function sender(): BelongsTo{
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver(): BelongsTo{
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function room(): BelongsTo{
        return $this->belongsTo(Room::class, 'room_id');
    }
}
