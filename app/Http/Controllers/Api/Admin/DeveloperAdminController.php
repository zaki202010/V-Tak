<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\AdminActivityLog;
use App\Models\Developer;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DeveloperAdminController extends ApiController
{
    public function index()
    {
        return $this->ok([
            'project' => Setting::get('dev_project', ''),
            'items' => Developer::orderBy('sort_order')->orderBy('id')->get()
                ->map(fn ($d) => $d->toArray() + ['photo_url' => $d->imageUrl()])->values(),
        ]);
    }

    public function store(Request $r)
    {
        $d = Developer::create($this->rules($r));
        AdminActivityLog::record($r->user(), 'developer.create', 'developer:'.$d->id);

        return $this->ok($d, 'Developer ditambahkan.', 201);
    }

    public function update(Request $r, Developer $developer)
    {
        $developer->update($this->rules($r));
        AdminActivityLog::record($r->user(), 'developer.update', 'developer:'.$developer->id);

        return $this->ok($developer, 'Developer diperbarui.');
    }

    public function destroy(Request $r, Developer $developer)
    {
        if ($developer->photo) {
            Storage::disk('public')->delete($developer->photo);
        }
        $developer->delete();
        AdminActivityLog::record($r->user(), 'developer.delete', 'developer:'.$developer->id);

        return $this->ok(null, 'Developer dihapus.');
    }

    public function image(Request $r, Developer $developer)
    {
        $r->validate(['image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048']]);
        $path = $r->file('image')->store('developers', 'public');
        if ($developer->photo) {
            Storage::disk('public')->delete($developer->photo);
        }
        $developer->update(['photo' => $path]);
        AdminActivityLog::record($r->user(), 'developer.photo', 'developer:'.$developer->id);

        return $this->ok(['photo_url' => $developer->imageUrl()], 'Foto diperbarui.');
    }

    public function imageDestroy(Request $r, Developer $developer)
    {
        if ($developer->photo) {
            Storage::disk('public')->delete($developer->photo);
            $developer->update(['photo' => null]);
        }
        AdminActivityLog::record($r->user(), 'developer.photo.delete', 'developer:'.$developer->id);

        return $this->ok(null, 'Foto dihapus.');
    }

    public function project(Request $r)
    {
        $d = $r->validate(['project' => ['nullable', 'string', 'max:3000']]);
        Setting::put('dev_project', $d['project'] ?? '');
        AdminActivityLog::record($r->user(), 'developer.project');

        return $this->ok(null, 'Tersimpan.');
    }

    private function rules(Request $r): array
    {
        $d = $r->validate([
            'name' => ['required', 'string', 'max:80'],
            'role' => ['nullable', 'string', 'max:100'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'skills' => ['nullable', 'string', 'max:500'],
            'experience' => ['nullable', 'string', 'max:2000'],
            'links' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['boolean'],
        ]);
        $d['sort_order'] = $d['sort_order'] ?? 0;

        return $d;
    }
}
