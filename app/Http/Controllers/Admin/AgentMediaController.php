<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AgentActivity;
use App\Models\AgentMedia;
use App\Models\AgentMediaFolder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AgentMediaController extends Controller
{
    public function index(Request $request)
    {
        $folderFilter = $request->integer('folder') ?: null;

        return view('admin.agents.media', [
            'media' => AgentMedia::with('folder')->when($folderFilter, fn ($q) => $q->where('folder_id', $folderFilter))->latest()->paginate(12)->withQueryString(),
            'folderOptions' => AgentMediaFolder::options(),
            'folders' => AgentMediaFolder::withCount(['media', 'children'])->orderBy('position')->orderBy('name')->get(),
            'folderFilter' => $folderFilter,
        ]);
    }

    public function storeFolder(Request $request)
    {
        $folder = AgentMediaFolder::create($this->validateFolder($request));
        AgentActivity::create(['user_id' => $request->user()->id, 'action' => 'media.folder_created', 'details' => ['folder_id' => $folder->id]]);

        return back()->with('success', "Folder \"{$folder->name}\" created.");
    }

    public function updateFolder(Request $request, AgentMediaFolder $folder)
    {
        $data = $this->validateFolder($request);
        if ($data['parent_id'] && in_array((int) $data['parent_id'], $folder->descendantIds(), true)) {
            throw ValidationException::withMessages(['parent_id' => 'A folder cannot be moved inside itself.']);
        }
        $folder->update($data);

        return back()->with('success', 'Folder updated.');
    }

    /** Deleting a folder keeps its content: items and subfolders move up one level. */
    public function destroyFolder(Request $request, AgentMediaFolder $folder)
    {
        DB::transaction(function () use ($folder) {
            AgentMedia::where('folder_id', $folder->id)->update(['folder_id' => $folder->parent_id]);
            AgentMediaFolder::where('parent_id', $folder->id)->update(['parent_id' => $folder->parent_id]);
            $folder->delete();
        });
        AgentActivity::create(['user_id' => $request->user()->id, 'action' => 'media.folder_deleted', 'details' => ['name' => $folder->name]]);

        return back()->with('success', "Folder \"{$folder->name}\" deleted. Its content moved up one level.");
    }

    private function validateFolder(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'parent_id' => ['nullable', 'integer', 'exists:agent_media_folders,id'],
            'position' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        return ['name' => $data['name'], 'parent_id' => $data['parent_id'] ?? null, 'position' => (int) ($data['position'] ?? 0)];
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
            'folder_id' => ['nullable', 'integer', 'exists:agent_media_folders,id'],
            ...($upload ? ['file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf,mp4', 'max:'.config('agents.media_max_kb')]] : []),
        ]);

        return $data;
    }
}
