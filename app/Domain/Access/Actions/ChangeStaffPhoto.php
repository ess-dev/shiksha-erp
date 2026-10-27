<?php

namespace App\Domain\Access\Actions;

use App\Domain\Access\Exceptions\GuardrailViolation;
use App\Domain\Access\Models\Staff;
use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Identity\Models\Identity;
use App\Domain\Shared\Action;
use App\Tenancy\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * A member of staff sets or removes their own photo on the tenant disk.
 */
class ChangeStaffPhoto extends Action
{
    /** Types are read from the file's content, never its extension. */
    public const ALLOWED_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    /** illustrative — verify: 2 MB per photo. */
    public const MAX_BYTES = 2 * 1024 * 1024;

    /**
     * Pass null to remove the photo. The earlier file is deleted after the new
     * one is saved.
     */
    public function handle(Identity $identity, ?UploadedFile $photo): Staff
    {
        $staff = Staff::query()->where('identity_ulid', $identity->ulid)->firstOrFail();
        $old = $staff->photo_path;
        $path = null;

        if ($photo !== null) {
            $mime = (string) $photo->getMimeType();

            if (! isset(self::ALLOWED_TYPES[$mime])) {
                throw new GuardrailViolation('Upload a JPEG, PNG or WebP photo.');
            }

            if ($photo->getSize() === false || $photo->getSize() > self::MAX_BYTES) {
                throw new GuardrailViolation('A photo can be at most 2 MB.');
            }

            $tenant = Tenant::current() ?? throw new GuardrailViolation('No school.');
            $name = strtolower((string) Str::ulid()).'.'.self::ALLOWED_TYPES[$mime];
            $path = "tenants/{$tenant->ulid}/staff/{$staff->ulid}/{$name}";

            Storage::disk('tenant')->putFileAs(dirname($path), $photo, $name);
        }

        try {
            $this->transaction(function () use ($staff, $path) {
                $staff->forceFill(['photo_path' => $path])->save();

                $this->audit(new AuditEntry('staff.photo_changed', 'staff', $staff->ulid, null, ['removed' => $path === null]));
            });
        } catch (Throwable $e) {
            if ($path !== null) {
                Storage::disk('tenant')->delete($path);
            }

            throw $e;
        }

        if ($old !== null) {
            Storage::disk('tenant')->delete($old);
        }

        return $staff;
    }
}
