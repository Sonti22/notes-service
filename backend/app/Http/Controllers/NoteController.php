<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListNotesRequest;
use App\Http\Requests\StoreNoteRequest;
use App\Http\Requests\UpdateNoteRequest;
use App\Http\Resources\NoteResource;
use App\Models\Note;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class NoteController extends Controller
{
    public function index(ListNotesRequest $request): AnonymousResourceCollection
    {
        $data = $request->validated();
        $query = Note::query();
        if (! empty($data['search'])) {
            // Escape LIKE wildcards: user input is a literal substring, not a pattern.
            $pattern = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $data['search']).'%';
            $query->where(function ($query) use ($pattern) {
                $query->whereRaw("title LIKE ? ESCAPE '\\'", [$pattern])
                    ->orWhereRaw("content LIKE ? ESCAPE '\\'", [$pattern]);
            });
        }

        return NoteResource::collection($query->orderByDesc('id')->paginate($data['per_page'] ?? 12));
    }

    public function store(StoreNoteRequest $request): JsonResponse
    {
        $note = Note::create($request->validated());

        return (new NoteResource($note))->response()->setStatusCode(201)
            ->header('Location', route('notes.show', $note));
    }

    public function show(Note $note): NoteResource
    {
        return new NoteResource($note);
    }

    public function update(UpdateNoteRequest $request, Note $note): NoteResource
    {
        $note->update($request->validated());

        return new NoteResource($note->refresh());
    }

    public function destroy(Note $note): Response
    {
        $note->delete();

        return response()->noContent();
    }
}
