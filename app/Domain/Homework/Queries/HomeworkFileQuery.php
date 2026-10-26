<?php

namespace App\Domain\Homework\Queries;

use App\Domain\Homework\Exceptions\HomeworkException;
use App\Domain\Homework\HomeworkAccess;
use App\Domain\Homework\Models\HomeworkAssignment;
use App\Domain\Homework\Models\HomeworkFile;
use App\Domain\Homework\Models\HomeworkSubmissionFile;
use App\Domain\Identity\Models\Identity;
use App\Domain\Students\Data\GuardianChildRef;

/**
 * Finds a homework file for someone who may open it, or a 404: staff who may
 * see the assignment's section, or a guardian whose child is in that section
 * (and, for uploaded work, whose child handed it in).
 */
class HomeworkFileQuery
{
    public function __construct(private readonly HomeworkAccess $access) {}

    public function attachmentForStaff(Identity $actor, string $fileUlid): HomeworkFile
    {
        $file = HomeworkFile::query()->where('ulid', $fileUlid)->first() ?? throw HomeworkException::notFound();
        $assignment = HomeworkAssignment::query()->find($file->homework_assignment_id) ?? throw HomeworkException::notFound();
        $this->access->assignmentOrFail($actor, $assignment->ulid);

        return $file;
    }

    public function submissionFileForStaff(Identity $actor, string $fileUlid): HomeworkSubmissionFile
    {
        $file = HomeworkSubmissionFile::query()->with('submission')->where('ulid', $fileUlid)->first() ?? throw HomeworkException::notFound();
        $assignment = HomeworkAssignment::query()->find($file->submission->homework_assignment_id) ?? throw HomeworkException::notFound();
        $this->access->assignmentOrFail($actor, $assignment->ulid);

        return $file;
    }

    public function attachmentForChild(GuardianChildRef $child, string $fileUlid): HomeworkFile
    {
        $file = HomeworkFile::query()->where('ulid', $fileUlid)->first() ?? throw HomeworkException::notFound();
        $assignment = HomeworkAssignment::query()->find($file->homework_assignment_id);

        if ($assignment === null || $assignment->section_id !== $child->sectionKey) {
            throw HomeworkException::notFound();
        }

        return $file;
    }

    public function submissionFileForChild(GuardianChildRef $child, string $fileUlid): HomeworkSubmissionFile
    {
        $file = HomeworkSubmissionFile::query()->with('submission')->where('ulid', $fileUlid)->first() ?? throw HomeworkException::notFound();
        $submission = $file->submission;
        $assignment = HomeworkAssignment::query()->find($submission->homework_assignment_id);

        if ($assignment === null || $submission->student_id !== $child->studentKey || $assignment->section_id !== $child->sectionKey) {
            throw HomeworkException::notFound();
        }

        return $file;
    }
}
