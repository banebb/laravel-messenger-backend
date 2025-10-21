<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ChatRole;
use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\RoomUser;
use App\Models\User;
use Illuminate\Http\Request;
use function PHPUnit\Framework\isEmpty;

class RoomController extends Controller
{
    public function createRoom(Request $request){
         $request->validate([
            'name'=>'required|string|max:255',
            'image_url'=>'nullable|string',
        ]);

        $room = Room::create([
            'name'=> $request->get('name'),
            'image_url'=> $request->get('image_url'),
        ]);

        RoomUser::create([
            'member_id' => request()->user()->id,
            'room_id' => $room->id,
            'role' => ChatRole::ADMIN->value,
        ]);

        return response()->json([
            'room'=>$room,
        ],201);
    }

    public function updateRoom(Request $request){
        $request->validate([
            'room_id'=>'required|integer|exists:rooms,id',
            'name'=>'nullable|string|max:255',
            'image_url'=>'nullable|string',
        ]);

        $room = Room::find($request->get('room_id'));

        if($request->has('name')){
            $room->name = $request->get('name');
        }

        if($request->has('image_url')){
            $room->image_url = $request->get('image_url');

        }

        $room->save();

        return response()->json([
            'message'=>'Room updated successfully',
            'room'=>$room
        ], 200);
    }

    public function deleteRoom(Request $request){
        $request->validate([
            'room_id'=>'required|integer|exists:rooms,id',
        ]);

        $room = Room::find($request->get('room_id'));
        $room->delete();

        return response()->json([
            'message'=>'Room deleted successfully'
        ], 200);
    }

    public function getRooms(Request $request){
        $rooms = Room::all();

        if(!$rooms || $rooms->isEmpty()){
            return response()->json([
                'message'=>'No rooms found'
            ], 404);
        }

        return response()->json([
            'rooms'=>$rooms
        ], 200);
    }

    public function getRoom(int $room_id){

        $room = Room::find($room_id);

        return response()->json([
            'room'=>$room
        ], 200);
    }

    public function getAllRoomsForMemeber(Request $request){
        $user = $request->user();

        $room_user_objs = RoomUser::where('member_id', $user->id)->get();

        $rooms = [];
        foreach($room_user_objs as $room_user){
            $rooms[] = Room::find($room_user->room_id);
        }

        return response()->json([
            'rooms'=>$rooms
        ], 200);
    }

    public function getRoomMembers(int $room_id, Request $request){
        $members_in_room = RoomUser::where('room_id', $room_id)->get();

        $members = [];
        foreach($members_in_room as $member){
            $members[] = User::find($member->member_id);
        }

        if (empty($members) ) {
            return response()->json([
                'message'=>'No members found in this room'
            ], 404);
        }

        return response()->json([
            'members'=>$members
        ], 200);
    }

    public function addRoomMember(Request $request){
        $request->validate([
            'member_id'=>'required|integer|exists:users,id',
            'room_id'=>'required|integer|exists:rooms,id',
        ]);

        RoomUser::create([
            'member_id' => request()->user()->id,
            'room_id' => request()->request->get('room_id'),
            'role' => ChatRole::ADMIN->value,
        ]);

        return response()->json([
            'message'=>'Room member added successfully'
        ], 201);
    }

    public function removeRoomMember(Request $request){
        $request->validate([
            'member_id'=>'required|integer|exists:users,id',
            'room_id'=>'required|integer|exists:rooms,id',
        ]);

        $member = RoomUser::find($request->get('member_id'))->where('room_id', $request->get('room_id'))->first();

        $member->delete();

        return response()->json([
            'message'=>'Room member removed successfully'
        ],200);
    }
}
