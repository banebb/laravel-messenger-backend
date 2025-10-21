<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Helpers\ChatRole;


class RoomUser extends Model
{
    protected $fillable = [
        'member_id',
        'room_id',
        'role'
    ];

    public function member() {
        return $this->belongsTo(User::class, 'member_id');
    }

    public function room() {
        return $this->belongsTo(Room::class, 'room_id');
    }
}
