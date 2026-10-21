<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Developer extends Model
{
    protected $fillable = ['name', 'role', 'photo', 'bio', 'skills', 'experience', 'links', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function imageUrl(): ?string
    {
        return $this->photo ? '/storage/'.$this->photo : null;
    }

    private function lines(?string $text): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $text))));
    }

    /** Tautan: satu baris "Label | URL". Hanya http(s) dan mailto yang diterima. */
    public function linkList(): array
    {
        $out = [];
        foreach ($this->lines($this->links) as $line) {
            $parts = explode('|', $line, 2);
            [$label, $url] = count($parts) === 2 ? [trim($parts[0]), trim($parts[1])] : [$line, $line];
            if ($label !== '' && preg_match('#^(https?://|mailto:)#i', $url)) {
                $out[] = ['label' => $label, 'url' => $url];
            }
        }

        return $out;
    }

    public function toPublic(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'role' => $this->role,
            'bio' => $this->bio,
            'photo_url' => $this->imageUrl(),
            'skills' => array_values(array_filter(array_map('trim', explode(',', (string) $this->skills)))),
            'experience' => $this->lines($this->experience),
            'links' => $this->linkList(),
        ];
    }
}
