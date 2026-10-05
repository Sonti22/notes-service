<?php

namespace Tests\Feature;

use App\Models\Note;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NoteApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_crud_lifecycle(): void
    {
        $created = $this->postJson('/api/notes', ['title' => 'Идея', 'content' => 'Создать сервис']);
        $created->assertCreated()->assertJsonPath('data.title', 'Идея')
            ->assertJsonStructure(['data' => ['id', 'title', 'content', 'created_at', 'updated_at']]);
        $id = $created->json('data.id');
        $created->assertHeader('Location', url('/api/notes/'.$id));
        $this->getJson('/api/notes/'.$id)->assertOk()->assertJsonPath('data.content', 'Создать сервис');
        $this->patchJson('/api/notes/'.$id, ['title' => 'Новая идея'])->assertOk()->assertJsonPath('data.content', 'Создать сервис');
        $this->putJson('/api/notes/'.$id, ['title' => 'План', 'content' => 'Готово'])->assertOk()->assertJsonPath('data.content', 'Готово');
        $this->getJson('/api/notes')->assertOk()->assertJsonPath('meta.total', 1);
        $this->deleteJson('/api/notes/'.$id)->assertNoContent();
        $this->assertDatabaseMissing('notes', ['id' => $id]);
        $this->getJson('/api/notes/'.$id)->assertNotFound();
    }

    public static function invalidNotes(): array
    {
        return [
            'missing fields' => [[], ['title', 'content']],
            'blank title' => [['title' => '   ', 'content' => 'text'], ['title']],
            'blank content' => [['title' => 'title', 'content' => " \n "], ['content']],
            'null' => [['title' => null, 'content' => null], ['title', 'content']],
            'wrong types' => [['title' => [], 'content' => 5], ['title', 'content']],
            'long title' => [['title' => str_repeat('я', 201), 'content' => 'text'], ['title']],
            'long content' => [['title' => 'title', 'content' => str_repeat('я', 10001)], ['content']],
        ];
    }

    #[DataProvider('invalidNotes')]
    public function test_create_validates_fields(array $payload, array $fields): void
    {
        $this->postJson('/api/notes', $payload)->assertUnprocessable()->assertJsonValidationErrors($fields);
        $this->assertDatabaseCount('notes', 0);
    }

    public function test_update_rejects_empty_and_invalid_payload_without_mutation(): void
    {
        $note = Note::create(['title' => 'Original', 'content' => 'Content']);
        $this->patchJson('/api/notes/'.$note->id, [])->assertUnprocessable()->assertJsonValidationErrors('note');
        $this->patchJson('/api/notes/'.$note->id, ['title' => null])->assertUnprocessable()->assertJsonValidationErrors('title');
        $this->putJson('/api/notes/'.$note->id, ['title' => 'New'])->assertUnprocessable()->assertJsonValidationErrors('content');
        $this->assertDatabaseHas('notes', ['id' => $note->id, 'title' => 'Original', 'content' => 'Content']);
    }

    public function test_boundary_lengths_and_mass_assignment_protection(): void
    {
        $this->postJson('/api/notes', ['title' => str_repeat('я', 200), 'content' => str_repeat('я', 10000), 'id' => 123, 'created_at' => '2000-01-01'])
            ->assertCreated()->assertJsonPath('data.id', 1);
    }

    public function test_list_paginates_with_stable_newest_first_order(): void
    {
        for ($i = 1; $i <= 15; $i++) {
            Note::create(['title' => 'Note '.$i, 'content' => 'Text']);
        }
        $this->getJson('/api/notes?per_page=10')->assertOk()->assertJsonCount(10, 'data')
            ->assertJsonPath('data.0.id', 15)->assertJsonPath('meta.total', 15)->assertJsonPath('meta.last_page', 2);
        $this->getJson('/api/notes?per_page=10&page=2')->assertOk()->assertJsonCount(5, 'data')->assertJsonPath('data.0.id', 5);
        $this->getJson('/api/notes?page=100')->assertOk()->assertJsonCount(0, 'data');
    }

    public static function invalidQueries(): array
    {
        return [['page=0', 'page'], ['page=-1', 'page'], ['page=abc', 'page'], ['page=1000001', 'page'], ['per_page=0', 'per_page'], ['per_page=101', 'per_page'], ['per_page[]=1', 'per_page'], ['search[]=x', 'search'], ['search='.str_repeat('a', 201), 'search']];
    }

    #[DataProvider('invalidQueries')]
    public function test_list_validates_query_parameters(string $query, string $field): void
    {
        $this->getJson('/api/notes?'.$query)->assertUnprocessable()->assertJsonValidationErrors($field);
    }

    public function test_search_matches_title_and_content_and_escapes_wildcards(): void
    {
        Note::create(['title' => 'A 100% plan', 'content' => 'alpha']);
        Note::create(['title' => 'Other', 'content' => '100% complete']);
        Note::create(['title' => 'Unrelated', 'content' => 'beta']);
        $this->getJson('/api/notes?search=100%25')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/notes?search=_')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/notes?search=%27%20OR%201%3D1')->assertOk()->assertJsonCount(0, 'data');
    }

    public static function invalidIds(): array
    {
        return [['0'], ['-1'], ['abc'], ['1.2'], ['999999999999999999999'], ['999']];
    }

    #[DataProvider('invalidIds')]
    public function test_show_update_delete_validate_identifiers(string $id): void
    {
        $this->getJson('/api/notes/'.$id)->assertNotFound()->assertHeader('Content-Type', 'application/json');
        $this->patchJson('/api/notes/'.$id, ['title' => 'T'])->assertNotFound();
        $this->deleteJson('/api/notes/'.$id)->assertNotFound();
    }

    public function test_errors_are_json_even_without_accept_header(): void
    {
        $this->post('/api/notes')->assertUnprocessable()->assertHeader('Content-Type', 'application/json');
        $this->get('/api/notes/abc')->assertNotFound()->assertHeader('Content-Type', 'application/json');
    }
}
