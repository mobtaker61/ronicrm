<?php

namespace App\Http\Controllers;

use App\Models\MediaFile;
use App\Models\MediaFolder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class MediaController extends Controller
{
    public function index(Request $request): Response
    {
        $folderId = $request->get('folder_id');
        $foldersTree = $this->getFoldersTree(null);
        $currentFolder = $folderId ? MediaFolder::withCount(['files', 'children'])->find($folderId) : null;
        $childFolders = MediaFolder::where('parent_id', $folderId)->withCount(['files', 'children'])->orderBy('name')->get();
        $files = MediaFile::where('folder_id', $folderId)->orderBy('name')->get()->map(fn ($f) => $this->formatFile($f));

        return Inertia::render('Media/Index', [
            'foldersTree' => $foldersTree,
            'currentFolder' => $currentFolder,
            'childFolders' => $childFolders,
            'files' => $files,
            'breadcrumbs' => $this->breadcrumbs($folderId),
        ]);
    }

    public function storeFolder(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:media_folders,id',
        ]);
        MediaFolder::create([
            'name' => $validated['name'],
            'parent_id' => $validated['parent_id'] ?? null,
            'created_by' => Auth::id(),
        ]);
        return redirect()->back()->with('success', 'پوشه ایجاد شد.');
    }

    public function updateFolder(Request $request, MediaFolder $folder)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);
        $folder->update(['name' => $validated['name']]);
        return redirect()->back()->with('success', 'نام پوشه تغییر کرد.');
    }

    public function storeFile(Request $request)
    {
        $request->validate([
            'file' => 'required_without:files|nullable|file|max:51200',
            'files' => 'nullable|array',
            'files.*' => 'nullable|file|max:51200',
            'folder_id' => 'nullable|exists:media_folders,id',
        ], [
            'file.file' => 'فایل به سرور نرسید. در سرور: php.ini را بررسی کنید (upload_max_filesize و post_max_size) و در Nginx مقدار client_max_body_size را افزایش دهید.',
            'files.*.file' => 'فایل به سرور نرسید. در سرور: php.ini را بررسی کنید (upload_max_filesize و post_max_size) و در Nginx مقدار client_max_body_size را افزایش دهید.',
            'file.max' => 'حجم فایل نباید بیشتر از ۵۰ مگابایت باشد.',
            'files.*.max' => 'حجم هر فایل نباید بیشتر از ۵۰ مگابایت باشد.',
        ]);
        $folderId = $request->input('folder_id') ?: null;
        $uploaded = 0;
        if ($request->hasFile('file')) {
            $uploaded = $this->storeOneFile($request->file('file'), $folderId);
        }
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                if ($file && $file->isValid()) {
                    $uploaded += $this->storeOneFile($file, $folderId);
                }
            }
        }
        return redirect()->back()->with('success', $uploaded ? "{$uploaded} فایل آپلود شد." : 'فایلی انتخاب نشد.');
    }

    private function storeOneFile($file, ?int $folderId): int
    {
        $path = $file->store('media/' . date('Y/m'), 'public');
        MediaFile::create([
            'folder_id' => $folderId,
            'name' => $file->getClientOriginalName(),
            'path' => $path,
            'disk' => 'public',
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'created_by' => Auth::id(),
        ]);
        return 1;
    }

    public function destroyFolder(Request $request, MediaFolder $folder)
    {
        $action = $request->input('action', 'empty'); // 'empty' | 'with_contents' | 'move_to_parent'

        $hasFiles = $folder->files()->exists();
        $hasChildren = $folder->children()->exists();

        if (!$hasFiles && !$hasChildren) {
            $folder->delete();
            return redirect()->back()->with('success', 'پوشه حذف شد.');
        }

        if ($action === 'with_contents') {
            $this->deleteFolderWithContents($folder);
            return redirect()->back()->with('success', 'پوشه و همهٔ محتویات آن حذف شد.');
        }

        if ($action === 'move_to_parent') {
            $parentId = $folder->parent_id;
            $folder->files()->update(['folder_id' => $parentId]);
            $folder->children()->update(['parent_id' => $parentId]);
            $folder->delete();
            return redirect()->back()->with('success', 'محتویات به پوشهٔ بالاتر منتقل شد و پوشه حذف شد.');
        }

        return redirect()->back()->with('error', 'این پوشه خالی نیست. لطفاً از صفحهٔ مدیا گزینهٔ حذف را با انتخاب نحوهٔ حذف انجام دهید.');
    }

    private function deleteFolderWithContents(MediaFolder $folder): void
    {
        foreach ($folder->files as $file) {
            Storage::disk($file->disk)->delete($file->path);
            $file->delete();
        }
        foreach ($folder->children as $child) {
            $this->deleteFolderWithContents($child);
        }
        $folder->delete();
    }

    public function destroyFile(MediaFile $mediaFile)
    {
        Storage::disk($mediaFile->disk)->delete($mediaFile->path);
        $mediaFile->delete();
        return redirect()->back()->with('success', 'File deleted.');
    }

    /**
     * API for picker modal: list folders and files (for Inbox / Campaigns / Templates).
     */
    public function list(Request $request)
    {
        $folderId = $request->get('folder_id');
        $childFolders = MediaFolder::where('parent_id', $folderId)->orderBy('name')->get(['id', 'name', 'parent_id']);
        $files = MediaFile::where('folder_id', $folderId)->orderBy('name')->get()->map(fn ($f) => $this->formatFile($f));
        $breadcrumbs = $this->breadcrumbs($folderId);
        return response()->json([
            'folders' => $childFolders,
            'files' => $files,
            'breadcrumbs' => $breadcrumbs,
        ]);
    }

    private function getFoldersTree(?int $parentId): array
    {
        $folders = MediaFolder::where('parent_id', $parentId)->withCount(['files', 'children'])->orderBy('name')->get();
        return $folders->map(function ($f) {
            return [
                'id' => $f->id,
                'name' => $f->name,
                'parent_id' => $f->parent_id,
                'files_count' => $f->files_count,
                'children_count' => $f->children_count,
                'children' => $this->getFoldersTree($f->id),
            ];
        })->toArray();
    }

    private function formatFile(MediaFile $f): array
    {
        return [
            'id' => $f->id,
            'name' => $f->name,
            'path' => $f->path,
            'url' => $f->url,
            'mime_type' => $f->mime_type,
            'size' => $f->size,
            'is_image' => $f->isImage(),
        ];
    }

    private function breadcrumbs(?int $folderId): array
    {
        $crumbs = [['id' => null, 'name' => 'همه فایل‌ها']];
        $current = $folderId ? MediaFolder::find($folderId) : null;
        $chain = [];
        $visited = [];
        $maxDepth = 50;
        while ($current && $maxDepth-- > 0) {
            if (isset($visited[$current->id])) {
                break;
            }
            $visited[$current->id] = true;
            array_unshift($chain, ['id' => $current->id, 'name' => $current->name]);
            $current = $current->parent;
        }
        return array_merge($crumbs, $chain);
    }
}
