<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\Room;
use App\Models\RoomUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MessageTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $receiver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->receiver = User::factory()->create();
    }

    public function test_user_can_send_private_message()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/messages/send-private', [
            'recivers' => [$this->receiver->id],
            'message_content' => 'Hello, this is a test message'
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['message']);

        $this->assertDatabaseHas('messages', [
            'sender_id' => $this->user->id,
            'receiver_id' => $this->receiver->id,
            'content' => 'Hello, this is a test message'
        ]);
    }

    public function test_user_can_send_private_message_to_multiple_receivers()
    {
        Sanctum::actingAs($this->user);

        $receiver2 = User::factory()->create();
        $receiver3 = User::factory()->create();

        $response = $this->postJson('/api/messages/send-private', [
            'recivers' => [$this->receiver->id, $receiver2->id, $receiver3->id],
            'message_content' => 'Hello everyone'
        ]);

        $response->assertStatus(201);

        $this->assertEquals(3, Message::where('sender_id', $this->user->id)->count());
    }

    public function test_send_private_message_requires_authentication()
    {
        $response = $this->postJson('/api/messages/send-private', [
            'recivers' => [$this->receiver->id],
            'message_content' => 'Hello'
        ]);

        $response->assertStatus(401);
    }

    public function test_user_can_send_room_message()
    {
        Sanctum::actingAs($this->user);

        $room = Room::create(['name' => 'Test Room', 'image_url' => null]);
        RoomUser::create([
            'room_id' => $room->id,
            'member_id' => $this->user->id,
            'role' => 'admin'
        ]);

        $response = $this->postJson('/api/messages/send-room', [
            'rooms_ids' => [$room->id],
            'message_content' => 'Hello room!'
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('messages', [
            'sender_id' => $this->user->id,
            'room_id' => $room->id,
            'content' => 'Hello room!'
        ]);
    }

    public function test_user_cannot_send_message_to_room_they_are_not_member_of()
    {
        Sanctum::actingAs($this->user);

        $room = Room::create(['name' => 'Test Room', 'image_url' => null]);
        // User is not added as member

        $response = $this->postJson('/api/messages/send-room', [
            'rooms_ids' => [$room->id],
            'message_content' => 'Hello room!'
        ]);

        $response->assertStatus(201); // Still returns 201 but skips the room

        $this->assertDatabaseMissing('messages', [
            'sender_id' => $this->user->id,
            'room_id' => $room->id
        ]);
    }

    public function test_user_can_edit_their_own_message()
    {
        Sanctum::actingAs($this->user);

        $message = Message::create([
            'sender_id' => $this->user->id,
            'reciver_id' => $this->receiver->id,
            'content' => 'Original message'
        ]);

        $response = $this->putJson('/api/messages/edit', [
            'message_id' => $message->id,
            'message_content' => 'Edited message'
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Message updated successfully'
            ]);

        $this->assertDatabaseHas('messages', [
            'id' => $message->id,
            'content' => 'Edited message'
        ]);
    }

    public function test_user_cannot_edit_someone_elses_message()
    {
        Sanctum::actingAs($this->user);

        $otherUser = User::factory()->create();
        $message = Message::create([
            'sender_id' => $otherUser->id,
            'reciver_id' => $this->receiver->id,
            'content' => 'Original message'
        ]);

        $response = $this->putJson('/api/messages/edit', [
            'message_id' => $message->id,
            'message_content' => 'Edited message'
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'Message was not sent by this user'
            ]);
    }

    public function test_user_can_delete_their_own_message()
    {
        Sanctum::actingAs($this->user);

        $message = Message::create([
            'sender_id' => $this->user->id,
            'reciver_id' => $this->receiver->id,
            'content' => 'Message to delete'
        ]);

        $response = $this->deleteJson('/api/messages/delete', [
            'message_id' => $message->id
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Message successfully deleted'
            ]);

        $this->assertDatabaseMissing('messages', [
            'id' => $message->id
        ]);
    }

    public function test_user_cannot_delete_someone_elses_message()
    {
        Sanctum::actingAs($this->user);

        $otherUser = User::factory()->create();
        $message = Message::create([
            'sender_id' => $otherUser->id,
            'reciver_id' => $this->receiver->id,
            'content' => 'Original message'
        ]);

        $response = $this->deleteJson('/api/messages/delete', [
            'message_id' => $message->id
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'Message was not sent by this user'
            ]);

        $this->assertDatabaseHas('messages', [
            'id' => $message->id
        ]);
    }

    public function test_user_can_forward_message_to_private_chat()
    {
        Sanctum::actingAs($this->user);

        $message = Message::create([
            'sender_id' => $this->receiver->id,
            'receiver_id' => $this->user->id,
            'content' => 'Original message to forward'
        ]);

        $newReceiver = User::factory()->create();

        $response = $this->postJson('/api/messages/forward-private', [
            'message_id' => $message->id,
            'recivers' => [$newReceiver->id]
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('messages', [
            'sender_id' => $this->user->id,
            'receiver_id' => $newReceiver->id,
            'content' => 'Original message to forward'
        ]);
    }

    public function test_user_can_forward_message_to_room()
    {
        Sanctum::actingAs($this->user);

        $message = Message::create([
            'sender_id' => $this->receiver->id,
            'receiver_id' => $this->user->id,
            'content' => 'Message to forward to room'
        ]);

        $room = Room::create(['name' => 'Test Room', 'image_url' => null]);
        RoomUser::create([
            'room_id' => $room->id,
            'member_id' => $this->user->id,
            'role' => 'admin'
        ]);

        $response = $this->postJson('/api/messages/forward-room', [
            'message_id' => $message->id,
            'rooms_ids' => [$room->id]
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('messages', [
            'sender_id' => $this->user->id,
            'room_id' => $room->id,
            'content' => 'Message to forward to room'
        ]);
    }

    public function test_user_can_get_all_messages()
    {
        Sanctum::actingAs($this->user);

        Message::create([
            'sender_id' => $this->user->id,
            'reciver_id' => $this->receiver->id,
            'content' => 'Message 1'
        ]);

        Message::create([
            'sender_id' => $this->receiver->id,
            'reciver_id' => $this->user->id,
            'content' => 'Message 2'
        ]);

        $response = $this->getJson('/api/messages');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'messages'
            ]);
    }

    public function test_user_can_get_message_by_id()
    {
        Sanctum::actingAs($this->user);

        $message = Message::create([
            'sender_id' => $this->user->id,
            'receiver_id' => $this->receiver->id,
            'content' => 'Test message'
        ]);

        $response = $this->getJson('/api/messages/' . $message->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message'
            ]);
    }

    public function test_user_can_get_room_chat_messages()
    {
        Sanctum::actingAs($this->user);

        $room = Room::create(['name' => 'Test Room', 'image_url' => null]);
        RoomUser::create([
            'room_id' => $room->id,
            'member_id' => $this->user->id,
            'role' => 'member'
        ]);

        Message::create([
            'sender_id' => $this->user->id,
            'room_id' => $room->id,
            'content' => 'Room message 1'
        ]);

        Message::create([
            'sender_id' => $this->receiver->id,
            'room_id' => $room->id,
            'content' => 'Room message 2'
        ]);

        $response = $this->getJson('/api/messages/room/' . $room->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data'
            ]);
    }

    public function test_user_cannot_get_room_chat_if_not_member()
    {
        Sanctum::actingAs($this->user);

        $room = Room::create(['name' => 'Test Room', 'image_url' => null]);
        // User is not added as member

        $response = $this->getJson('/api/messages/room/' . $room->id);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'You are not the member of the room'
            ]);
    }

    public function test_validation_fails_when_message_content_exceeds_max_length()
    {
        Sanctum::actingAs($this->user);

        $longMessage = str_repeat('a', 1001); // Exceeds 1000 char limit

        $response = $this->postJson('/api/messages/send-private', [
            'recivers' => [$this->receiver->id],
            'message_content' => $longMessage
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['message_content']);
    }

    public function test_validation_fails_when_receivers_array_is_empty()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/messages/send-private', [
            'recivers' => [],
            'message_content' => 'Hello'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['recivers']);
    }
}

