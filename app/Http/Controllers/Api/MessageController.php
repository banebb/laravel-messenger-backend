<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Message;
use App\Models\RoomUser;
use Illuminate\Container\Attributes\Auth;
use Illuminate\Mail\Mailables\Content;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

class MessageController extends Controller
{
    public function sendPrivate(Request $request){
        $request->validate([
            'recivers' => 'required|array|min:1',
            'recivers.*' => 'integer|exists:users,id',
            'message_content'=> 'required|string|max:1000'
        ]);

        $sender = $request->user();

        $successfull_messages = 0;

        foreach($request->recivers as $reciverId) {
            Message::create([
                'sender_id' => $sender->id,
                'receiver_id' => $reciverId,
                'room_id' => null,
                'content'=> $request->message_content
            ]);

            $successfull_messages++;
        }

        return response()->json([
            'message'=> $successfull_messages . '/' . count($request->recivers) . ' messages sent successfully',
        ], 201);

    }

    public function sendRoom(Request $request){
        $request->validate([
            'rooms_ids'=> 'required|array|min:1',
            'rooms_ids.*'=> 'integer|exists:rooms,id',
            'message_content'=> 'required|string|max:1000'
        ]);

        $sender = $request->user();
        $successfull_messages = 0;

        foreach($request->rooms_ids as $room_id){
            if(!RoomUser::where('room_id', $room_id)
                    ->where('member_id', $sender->id)
                    ->exists())
                continue;

            Message::create([
                'sender_id' => $sender->id,
                'reciver_id' => null,
                'room_id' => $room_id,
                'content'=> $request->message_content
            ]);

            $successfull_messages++;
        }

        return response()->json([
            'message'=> $successfull_messages . '/' . count($request->rooms_ids) . ' messages sent successfully',
        ], 201);

    }

    public function edit(Request $request){
        $request->validate([
            'message_id'=> 'required|integer|exists:messages,id',
            'message_content'=> 'required|string|max:1000'
        ]);

        $sender = $request->user();

        $message = Message::findOrFail($request->message_id);

        if($message->sender_id !== $sender->id){
            return response()->json([
                'message' => 'Message was not sent by this user',
                'data'=> $message
            ],400);
        }

        $message->update(['content' => $request->message_content]);

        return response()->json([
            'message' => 'Message updated successfully',
            'data'=> $message
        ], 200);
    }

    public function delete(Request $request){
        $request->validate([
            'message_id'=> 'required|integer|exists:messages,id',
        ]);

        $sender = $request->user();

        $message = Message::findOrFail($request->message_id);

        if($message->sender_id !== $sender->id){
            return response()->json([
                'message' => 'Message was not sent by this user',
            ],400);
        }

        $message->delete();

        return response()->json([
            'message' => 'Message successfully deleted'
        ], 200);
    }


    public function forwardPrivate(Request $request) {
        $request->validate([
            'message_id'=> 'required|integer|exists:messages,id',
            'recivers' => 'required|array|min:1',
            'recivers.*' => 'integer|exists:users,id'
        ]);

        $sender = $request->user();

        $message = Message::findOrFail($request->message_id);

        $successfull_messages = 0;

        foreach($request->recivers as $reciverId) {
            Message::create([
                'sender_id'=> $sender->id,
                'receiver_id'=> $reciverId,
                'room_id'=>null,
                'content'=>$message->content
            ]);

            $successfull_messages++;
        }

        return response()->json([
            'message'=> $successfull_messages . '/' . count($request->recivers) . ' messages sent successfully'
        ], 201);
    }

    public function forwardRoom(Request $request) {
        $request->validate([
            'rooms_ids'=> 'required|array|min:1',
            'rooms_ids.*'=> 'integer|exists:rooms,id',
            'message_id'=> 'required|integer|exists:messages,id'
        ]);

        $sender = $request->user();

        $message = Message::findOrFail($request->message_id);

        $successfull_messages = 0;

        foreach($request->rooms_ids as $room_id) {

            if(!RoomUser::where('room_id', $room_id)
                    ->where('member_id', $sender->id)
                    ->exists())
                continue;

            Message::create([
                'sender_id'=> $sender->id,
                'reciver_id'=> null,
                'room_id'=>$room_id,
                'content'=>$message->content
            ]);

            $successfull_messages++;
        }

        return response()->json([
            'message'=> $successfull_messages . '/' . count($request->rooms_ids) . ' messages sent successfully'
        ], 201);
    }

    public function getAll() {
        $messages = Message::all();

        if($messages->isEmpty()) {
            return response()->json([
                'message' => 'No messages found'
            ], 404);
        }

        return response()->json([
            'messages'=> $messages
        ], 200);
    }

    public function getById(int $message_id) {
        return response()->json([
            'message'=> Message::findOrFail($message_id)
        ], 200);
    }

    public function getPrivateChat(int $receiver_id, Request $request){

        $sender = $request->user();

        $messages = Message::where('sender_id', $sender->id)->where('receiver_id', $receiver_id)->get();

        if($messages->isEmpty()) {
            return response()->json([
               'message'=> 'Chat not found'
            ], 404);
        }

        return response()->json([
            'message' => 'Chat found',
            'data'=> $messages,
        ], 200);
    }

    public function getRoomChat(int $room_id, Request $request){

        $sender = $request->user();

        if(!RoomUser::where('room_id', $room_id)
                    ->where('member_id', $sender->id)
                    ->exists())
                return response()->json([
                    'message' => 'You are not the member of the room',
                    'data'=> null,
                ], 403);

        $messages = Message::where('room_id', $room_id)->get();

        if($messages->isEmpty()) {
            return response()->json([
               'message'=> 'Chat not found'
            ], 404);
        }

        return response()->json([
            'message' => 'Chat found',
            'data'=> $messages,
        ], 200);
    }

}
