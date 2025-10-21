<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [App\Http\Controllers\Api\AuthController::class, 'register']);
Route::post('/login', [App\Http\Controllers\Api\AuthController::class, 'login']);

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('messages')->middleware('auth:sanctum')->group(function () {
    Route::post('/send-private', [App\Http\Controllers\Api\MessageController::class, 'sendPrivate']);
    Route::post('/send-room', [App\Http\Controllers\Api\MessageController::class, 'sendRoom']);
    Route::put('/edit', [App\Http\Controllers\Api\MessageController::class, 'edit']);
    Route::delete('/delete', [App\Http\Controllers\Api\MessageController::class, 'delete']);
    Route::post('forward-private' , [App\Http\Controllers\Api\MessageController::class, 'forwardPrivate']);
    Route::post('forward-room' , [App\Http\Controllers\Api\MessageController::class, 'forwardRoom']);
    Route::get('/', [App\Http\Controllers\Api\MessageController::class, 'getAll']);
    Route::get('/{message_id}', [App\Http\Controllers\Api\MessageController::class, 'getById']);
    Route::get('/room/{room_id}', [App\Http\Controllers\Api\MessageController::class, 'getRoomChat']);
});

Route::prefix('rooms')->middleware('auth:sanctum')->group(function () {
    Route::post('/create', [App\Http\Controllers\Api\RoomController::class, 'createRoom']);
    Route::put('/update', [App\Http\Controllers\Api\RoomController::class, 'updateRoom']);
    Route::delete('/delete', [App\Http\Controllers\Api\RoomController::class, 'deleteRoom']);
    Route::get('/', [App\Http\Controllers\Api\RoomController::class, 'getRooms']);
    Route::get('/my-rooms', [App\Http\Controllers\Api\RoomController::class, 'getAllRoomsForMemeber']);
    Route::get('/{room_id}', [App\Http\Controllers\Api\RoomController::class, 'getRoom']);
    Route::post('/add-member', [App\Http\Controllers\Api\RoomController::class, 'addRoomMember']);
    Route::post('/remove-member', [App\Http\Controllers\Api\RoomController::class, 'removeRoomMember']);
    Route::get('/{room_id}/members', [App\Http\Controllers\Api\RoomController::class, 'getRoomMembers']);
});

