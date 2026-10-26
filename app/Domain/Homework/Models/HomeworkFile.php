<?php

namespace App\Domain\Homework\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;

/**
 * A worksheet, photo or document the teacher attached to an assignment.
 *
 * @property int $id
 * @property string $ulid
 * @property int $homework_assignment_id
 * @property string $file_path
 * @property string $file_name
 * @property string $mime_type
 * @property int $size_bytes
 * @property int $position
 */
class HomeworkFile extends Model
{
    use UsesTenantConnection;

    protected $table = 'homework_files';

    /** @var list<string> */
    protected $fillable = ['ulid', 'homework_assignment_id', 'file_path', 'file_name', 'mime_type', 'size_bytes', 'position'];

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }
}
