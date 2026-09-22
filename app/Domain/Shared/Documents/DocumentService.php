<?php

declare(strict_types=1);

namespace App\Domain\Shared\Documents;

use App\Domain\Shared\Document;
use App\Domain\Shared\Exceptions\RuleViolation;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Stores evidence (M16, Document B §8): type allow-list, size cap, virus scan,
 * storage outside the web root, and a SHA-256 recorded for tamper evidence. The
 * object key is the hash itself, so a stored file can always be re-verified and the
 * same file uploaded twice is stored once.
 */
final class DocumentService
{
    public function __construct(private readonly VirusScanner $scanner) {}

    public function attach(User $actor, Model $documentable, UploadedFile $file, string $docType, ?string $docRef = null, ?string $docDate = null, ?int $lineId = null): Document
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $allowed = config('shh.documents.allowed_mimes', []);

        if (! in_array($extension, $allowed, true)) {
            throw RuleViolation::because('M16', 'rules.documents.type_not_allowed', ['types' => implode(', ', $allowed)]);
        }

        if ($file->getSize() > (int) config('shh.documents.max_kilobytes', 20480) * 1024) {
            throw RuleViolation::because('M16', 'rules.documents.too_large');
        }

        if (! in_array($docType, Document::TYPES, true)) {
            throw RuleViolation::because('M16', 'rules.documents.bad_type');
        }

        if (! $this->scanner->isClean($file->getRealPath())) {
            throw RuleViolation::because('M16', 'rules.documents.infected');
        }

        $sha = hash_file('sha256', $file->getRealPath());
        $key = substr($sha, 0, 2).'/'.substr($sha, 2, 2).'/'.$sha.'.'.$extension;
        $disk = (string) config('shh.documents.disk', 'documents');

        if (! Storage::disk($disk)->exists($key)) {
            Storage::disk($disk)->putFileAs(dirname($key), $file, basename($key));
        }

        return Document::query()->create([
            'documentable_type' => $documentable->getMorphClass(),
            'documentable_id' => $documentable->getKey(),
            'journal_line_id' => $lineId,
            'doc_type' => $docType,
            'doc_ref' => $docRef,
            'doc_date' => $docDate,
            'disk' => $disk,
            'object_key' => $key,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => (string) $file->getMimeType(),
            'size_bytes' => (int) $file->getSize(),
            'sha256' => $sha,
            'uploaded_by' => $actor->id,
            'uploaded_at' => now(),
        ]);
    }

    /** Re-hashes the stored object: false means it no longer matches what was uploaded. */
    public function verify(Document $document): bool
    {
        $contents = Storage::disk($document->disk)->get($document->object_key);

        return $contents !== null && hash_equals($document->sha256, hash('sha256', $contents));
    }
}
