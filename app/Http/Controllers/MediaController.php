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
        $this->authorize('viewAny', MediaFolder::class);

        $folderId = $request->get('folder_id');
        $currentFolder = $folderId ? $this->resolveVisibleFolder((int) $folderId) : null;
        if ($folderId && ! $currentFolder) {
            abort(404);
        }

        $foldersTree = $this->getFoldersTree(null);
        $childFolders = $this->visibleFoldersQuery()
            ->where('parent_id', $currentFolder?->id)
            ->withCount(['files', 'children'])
            ->orderBy('name')
            ->get();
        $files = $this->visibleFilesQuery()
            ->where('folder_id', $currentFolder?->id)
            ->orderBy('name')
            ->get()
            ->map(fn ($f) => $this->formatFile($f));

        return Inertia::render('Media/Index', [
            'foldersTree' => $foldersTree,
            'currentFolder' => $currentFolder,
            'childFolders' => $childFolders,
            'files' => $files,
            'breadcrumbs' => $this->breadcrumbs($folderId),
            'canCreateSystemScope' => Auth::user()?->hasRole('super_admin') ?? false,
            'defaultScopeType' => MediaFolder::SCOPE_ORGANIZATION,
        ]);
    }

    public function storeFolder(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:media_folders,id',
            'scope_type' => 'nullable|in:organization,system',
        ]);

        $parent = null;
        $scopeType = $validated['scope_type'] ?? MediaFolder::SCOPE_ORGANIZATION;
        $organizationId = Auth::user()?->current_organization_id;

        if (! empty($validated['parent_id'])) {
            $parent = $this->resolveVisibleFolder((int) $validated['parent_id']);
            if (! $parent) {
                abort(404);
            }
            $scopeType = $parent->scope_type;
            $organizationId = $parent->organization_id;
        }

        $this->authorize('createForScope', [MediaFolder::class, $scopeType, $organizationId]);

        MediaFolder::create([
            'organization_id' => $scopeType === MediaFolder::SCOPE_SYSTEM ? null : $organizationId,
            'scope_type' => $scopeType,
            'name' => $validated['name'],
            'parent_id' => $validated['parent_id'] ?? null,
            'created_by' => Auth::id(),
        ]);
        return redirect()->back()->with('success', 'پوشه ایجاد شد.');
    }

    public function updateFolder(Request $request, MediaFolder $folder)
    {
        $folder = $this->resolveVisibleFolder($folder->id);
        if (! $folder) {
            abort(404);
        }
        $this->authorize('update', $folder);

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
            'scope_type' => 'nullable|in:organization,system',
        ], [
            'file.file' => 'فایل به سرور نرسید. در سرور: php.ini را بررسی کنید (upload_max_filesize و post_max_size) و در Nginx مقدار client_max_body_size را افزایش دهید.',
            'files.*.file' => 'فایل به سرور نرسید. در سرور: php.ini را بررسی کنید (upload_max_filesize و post_max_size) و در Nginx مقدار client_max_body_size را افزایش دهید.',
            'file.max' => 'حجم فایل نباید بیشتر از ۵۰ مگابایت باشد.',
            'files.*.max' => 'حجم هر فایل نباید بیشتر از ۵۰ مگابایت باشد.',
        ]);

        $folder = null;
        $scopeType = $request->input('scope_type', MediaFile::SCOPE_ORGANIZATION);
        $organizationId = Auth::user()?->current_organization_id;

        if ($request->filled('folder_id')) {
            $folder = $this->resolveVisibleFolder((int) $request->input('folder_id'));
            if (! $folder) {
                abort(404);
            }
            $scopeType = $folder->scope_type;
            $organizationId = $folder->organization_id;
        }

        $this->authorize('createForScope', [MediaFile::class, $scopeType, $organizationId]);

        $folderId = $folder?->id;
        $uploaded = 0;
        if ($request->hasFile('file')) {
            $uploaded = $this->storeOneFile($request->file('file'), $folderId, $scopeType, $organizationId);
        }
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                if ($file && $file->isValid()) {
                    $uploaded += $this->storeOneFile($file, $folderId, $scopeType, $organizationId);
                }
            }
        }
        return redirect()->back()->with('success', $uploaded ? "{$uploaded} فایل آپلود شد." : 'فایلی انتخاب نشد.');
    }

    private function storeOneFile($file, ?int $folderId, string $scopeType, ?int $organizationId): int
    {
        $path = $file->store('media/' . date('Y/m'), 'public');
        MediaFile::create([
            'organization_id' => $scopeType === MediaFile::SCOPE_SYSTEM ? null : $organizationId,
            'scope_type' => $scopeType,
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
        $folder = $this->resolveVisibleFolder($folder->id);
        if (! $folder) {
            abort(404);
        }
        $this->authorize('delete', $folder);

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
        $mediaFile = $this->resolveVisibleFile($mediaFile->id);
        if (! $mediaFile) {
            abort(404);
        }
        $this->authorize('delete', $mediaFile);

        Storage::disk($mediaFile->disk)->delete($mediaFile->path);
        $mediaFile->delete();
        return redirect()->back()->with('success', 'File deleted.');
    }

    /**
     * API for picker modal: list folders and files (for Inbox / Campaigns / Templates).
     */
    public function list(Request $request)
    {
        $this->authorize('viewAny', MediaFolder::class);

        $folderId = $request->get('folder_id') ? (int) $request->get('folder_id') : null;
        $currentFolder = $folderId ? $this->resolveVisibleFolder($folderId) : null;
        if ($folderId && ! $currentFolder) {
            abort(404);
        }

        $childFolders = $this->visibleFoldersQuery()
            ->where('parent_id', $currentFolder?->id)
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id', 'scope_type', 'organization_id']);
        $files = $this->visibleFilesQuery()
            ->where('folder_id', $currentFolder?->id)
            ->orderBy('name')
            ->get()
            ->map(fn ($f) => $this->formatFile($f));
        $breadcrumbs = $this->breadcrumbs($currentFolder?->id);
        return response()->json([
            'folders' => $childFolders,
            'files' => $files,
            'breadcrumbs' => $breadcrumbs,
        ]);
    }

    private function getFoldersTree(?int $parentId): array
    {
        $folders = $this->visibleFoldersQuery()
            ->where('parent_id', $parentId)
            ->withCount(['files', 'children'])
            ->orderBy('name')
            ->get();
        return $folders->map(function ($f) {
            return [
                'id' => $f->id,
                'name' => $f->name,
                'parent_id' => $f->parent_id,
                'scope_type' => $f->scope_type,
                'organization_id' => $f->organization_id,
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
            'url' => $f->getUrlAttribute(),
            'mime_type' => $f->mime_type,
            'size' => $f->size,
            'is_image' => $f->isImage(),
            'scope_type' => $f->scope_type,
            'organization_id' => $f->organization_id,
        ];
    }

    private function breadcrumbs(?int $folderId): array
    {
        $crumbs = [['id' => null, 'name' => 'همه فایل‌ها']];
        $current = $folderId ? $this->resolveVisibleFolder($folderId) : null;
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

    private function visibleFoldersQuery()
    {
        return MediaFolder::query()->visibleTo(Auth::user());
    }

    private function visibleFilesQuery()
    {
        return MediaFile::query()->visibleTo(Auth::user());
    }

    private function resolveVisibleFolder(int $folderId): ?MediaFolder
    {
        return $this->visibleFoldersQuery()->withCount(['files', 'children'])->find($folderId);
    }

    private function resolveVisibleFile(int $fileId): ?MediaFile
    {
        return $this->visibleFilesQuery()->find($fileId);
    }
}
