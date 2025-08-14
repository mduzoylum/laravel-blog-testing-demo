<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class PostController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $posts = Post::with(['user', 'comments'])
            ->published()
            ->paginate(15);

        return response()->json([
            'data' => $posts->items(),
            'pagination' => [
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'per_page' => $posts->perPage(),
                'total' => $posts->total()
            ]
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {

        if (!auth()->check()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string|min:10',
            'status' => 'sometimes|in:draft,published'
        ], [
            'title.required' => 'Başlık zorunludur',
            'content.required' => 'İçerik zorunludur',
            'content.min' => 'İçerik en az 10 karakter olmalıdır',
            'status.in' => 'Geçersiz durum değeri'
        ]);

        $post = Post::create(array_merge($validated, [
            'user_id' => auth()->id(),
            'status' => $validated['status'] ?? 'draft'
        ]));

        return response()->json($post, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Post $post): JsonResponse
    {
        $this->authorize('view', $post);
        
        $post->load(['user', 'comments.user']);
        
        return response()->json($post);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Post $post)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Post $post): JsonResponse
    {
        $this->authorize('update', $post);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'content' => 'sometimes|string|min:10',
            'status' => 'sometimes|in:draft,published'
        ], [
            'content.min' => 'İçerik en az 10 karakter olmalıdır',
            'status.in' => 'Geçersiz durum değeri'
        ]);

        $post->update($validated);

        return response()->json($post);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Post $post): JsonResponse
    {
        $this->authorize('delete', $post);

        $post->delete();

        return response()->json(null, 204);
    }

    /**
     * Publish a draft post
     */
    public function publish(Post $post): JsonResponse
    {
        $this->authorize('update', $post);

        $post->publish();

        return response()->json([
            'message' => 'Post published successfully',
            'post' => $post->fresh()
        ]);
    }
}
