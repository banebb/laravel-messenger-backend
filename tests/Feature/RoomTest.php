<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\RoomUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoomTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_user_can_create_room()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/rooms/create', [
            'name' => 'Test Room',
            'image_url' => 'https://example.com/image.jpg'
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'room' => ['id', 'name', 'image_url']
            ]);

        $this->assertDatabaseHas('rooms', [
            'name' => 'Test Room'
        ]);

        // Check that creator is added as admin
        $this->assertDatabaseHas('room_users', [
            'member_id' => $this->user->id,
            'role' => 'admin'
        ]);
    }

    public function test_user_can_create_room_without_image()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/rooms/create', [
            'name' => 'Test Room'
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('rooms', [
            'name' => 'Test Room'
        ]);
    }

    public function test_create_room_requires_authentication()
    {
        $response = $this->postJson('/api/rooms/create', [
            'name' => 'Test Room'
        ]);

        $response->assertStatus(401);
    }

    public function test_create_room_requires_name()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/rooms/create', [
            'image_url' => 'https://example.com/image.jpg'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_user_can_update_room()
    {
        Sanctum::actingAs($this->user);

        $room = Room::create([
            'name' => 'Original Name',
            'image_url' => null
        ]);

        $response = $this->putJson('/api/rooms/update', [
            'room_id' => $room->id,
            'name' => 'Updated Name',
            'image_url' => 'https://example.com/new-image.jpg'
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Room updated successfully',
                'room' => [
                    'id' => $room->id,
                    'name' => 'Updated Name',
                    'image_url' => 'https://example.com/new-image.jpg'
                ]
            ]);

        $this->assertDatabaseHas('rooms', [
            'id' => $room->id,
            'name' => 'Updated Name'
        ]);
    }

    public function test_user_can_update_only_room_name()
    {
        Sanctum::actingAs($this->user);

        $room = Room::create([
            'name' => 'Original Name',
            'image_url' => 'https://example.com/original.jpg'
        ]);

        $response = $this->putJson('/api/rooms/update', [
            'room_id' => $room->id,
            'name' => 'Updated Name'
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('rooms', [
            'id' => $room->id,
            'name' => 'Updated Name',
            'image_url' => 'https://example.com/original.jpg'
        ]);
    }

    public function test_user_can_delete_room()
    {
        Sanctum::actingAs($this->user);

        $room = Room::create([
            'name' => 'Room to Delete',
            'image_url' => null
        ]);

        $response = $this->deleteJson('/api/rooms/delete', [
            'room_id' => $room->id
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Room deleted successfully'
            ]);

        $this->assertDatabaseMissing('rooms', [
            'id' => $room->id
        ]);
    }

    public function test_user_can_get_all_rooms()
    {
        Sanctum::actingAs($this->user);

        Room::create(['name' => 'Room 1', 'image_url' => null]);
        Room::create(['name' => 'Room 2', 'image_url' => null]);
        Room::create(['name' => 'Room 3', 'image_url' => null]);

        $response = $this->getJson('/api/rooms');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'rooms'
            ])
            ->assertJsonCount(3, 'rooms');
    }

    public function test_get_rooms_returns_404_when_no_rooms_exist()
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/rooms');

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'No rooms found'
            ]);
    }

    public function test_user_can_get_room_by_id()
    {
        Sanctum::actingAs($this->user);

        $room = Room::create([
            'name' => 'Test Room',
            'image_url' => 'https://example.com/image.jpg'
        ]);

        $response = $this->getJson('/api/rooms/' . $room->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'room' => ['id', 'name']
            ])
            ->assertJson([
                'room' => [
                    'id' => $room->id,
                    'name' => 'Test Room'
                ]
            ]);
    }

    public function test_user_can_get_their_rooms()
    {
        Sanctum::actingAs($this->user);

        $room1 = Room::create(['name' => 'User Room 1', 'image_url' => null]);
        $room2 = Room::create(['name' => 'User Room 2', 'image_url' => null]);
        $room3 = Room::create(['name' => 'Other Room', 'image_url' => null]);

        RoomUser::create([
            'room_id' => $room1->id,
            'member_id' => $this->user->id,
            'role' => 'admin'
        ]);

        RoomUser::create([
            'room_id' => $room2->id,
            'member_id' => $this->user->id,
            'role' => 'member'
        ]);

        // room3 - user is not a member

        $response = $this->getJson('/api/rooms/my-rooms');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'rooms'
            ]);
    }

    public function test_user_can_add_room_member()
    {
        Sanctum::actingAs($this->user);

        $room = Room::create(['name' => 'Test Room', 'image_url' => null]);
        $newMember = User::factory()->create();

        $response = $this->postJson('/api/rooms/add-member', [
            'member_id' => $newMember->id,
            'room_id' => $room->id
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Room member added successfully'
            ]);
    }

    public function test_user_can_remove_room_member()
    {
        Sanctum::actingAs($this->user);

        $room = Room::create(['name' => 'Test Room', 'image_url' => null]);
        $member = User::factory()->create();

        $roomUser = RoomUser::create([
            'room_id' => $room->id,
            'member_id' => $member->id,
            'role' => 'member'
        ]);

        $response = $this->postJson('/api/rooms/remove-member', [
            'member_id' => $roomUser->id,
            'room_id' => $room->id
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Room member removed successfully'
            ]);
    }

    public function test_user_can_get_room_members()
    {
        Sanctum::actingAs($this->user);

        $room = Room::create(['name' => 'Test Room', 'image_url' => null]);

        $member1 = User::factory()->create();
        $member2 = User::factory()->create();

        RoomUser::create([
            'room_id' => $room->id,
            'member_id' => $member1->id,
            'role' => 'admin'
        ]);

        RoomUser::create([
            'room_id' => $room->id,
            'member_id' => $member2->id,
            'role' => 'member'
        ]);

        $response = $this->getJson('/api/rooms/' . $room->id . '/members');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'members'
            ]);
    }

    public function test_update_room_validation_fails_for_invalid_room_id()
    {
        Sanctum::actingAs($this->user);

        $response = $this->putJson('/api/rooms/update', [
            'room_id' => 99999,
            'name' => 'Updated Name'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['room_id']);
    }

    public function test_delete_room_validation_fails_for_invalid_room_id()
    {
        Sanctum::actingAs($this->user);

        $response = $this->deleteJson('/api/rooms/delete', [
            'room_id' => 99999
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['room_id']);
    }

    public function test_room_name_cannot_exceed_max_length()
    {
        Sanctum::actingAs($this->user);

        $longName = str_repeat('a', 256);

        $response = $this->postJson('/api/rooms/create', [
            'name' => $longName
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }
}

