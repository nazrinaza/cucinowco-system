<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AgentActivity;
use App\Models\AgentMedia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AgentMediaController extends Controller
{
    public function index()
    {
        return view('admin.agents.media', ['media' => AgentMedia::latest()->paginate(12)]);
    }

    public function store(Request $request)
    {
        $data = $this->validateMedia($request);
        $file = $request->file('file');
        if (! $file && blank($data['caption'] ?? null)) {
            return back()->withErrors(['file' => 'Add a file or a ready-to-copy caption.'])->withInput();
        }
        if ($file) {
            $path = $file->store('agent-media', 'local');
            abort_unless($path, 500, 'The file could not be stored.');
            $data = [...$data, 'path' => $path, 'original_name' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType(), 'size' => $file->getSize()];
        }
        unset($data['file']);
        $media = AgentMedia::create([...$data, 'created_by_user_id' => $request->user()->id]);
        AgentActivity::create(['user_id' => $request->user()->id, 'action' => 'media.created', 'details' => ['media_id' => $media->id]]);

        return back()->with('success', 'Marketing content saved. Published content is now available to approved agents.');
    }

    public function update(Request $request, AgentMedia $media)
    {
        $data = $this->validateMedia($request, false);
        $media->update($data);
        AgentActivity::create(['user_id' => $request->user()->id, 'action' => 'media.updated', 'details' => ['media_id' => $media->id, 'published' => $media->is_published]]);

        return back()->with('success', 'Content updated.');
    }

    public function download(AgentMedia $media)
    {
        abort_unless($media->path && Storage::disk('local')->exists($media->path), 404);

        return Storage::disk('local')->download($media->path, $media->original_name, ['X-Content-Type-Options' => 'nosniff']);
    }

    private function validateMedia(Request $request, bool $upload = true): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'category' => ['required', Rule::in(['social', 'poster', 'video', 'caption', 'guide'])],
            'description' => ['nullable', 'string', 'max:2000'],
            'caption' => ['nullable', 'string', 'max:5000'],
            'is_published' => ['required', 'boolean'],
            'expires_at' => ['nullable', 'date'],
            ...($upload ? ['file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf,mp4', 'max:'.config('agents.media_max_kb')]] : []),
        ]);

        return $data;
    }
}
