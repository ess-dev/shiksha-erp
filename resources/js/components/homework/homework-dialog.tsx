import { router } from '@inertiajs/react';
import { Paperclip } from 'lucide-react';
import { useRef, useState } from 'react';
import { HOMEWORK_ACCEPT } from '@/components/homework/due-label';
import {
    HomeworkFileLinks,
    PickedFiles,
} from '@/components/homework/homework-files';
import InputError from '@/components/input-error';
import Field, { selectClassName } from '@/components/school/field';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { resizeImage } from '@/lib/resize-image';
import { cn } from '@/lib/utils';
import { store, update } from '@/routes/homework';
import type {
    HomeworkDetail,
    HomeworkFormOptions,
    SubmissionType,
} from '@/types';

const textareaClassName =
    'min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';

/**
 * Sets a new assignment, or (with `editing`) changes one the teacher already
 * set: title, instructions, due date, attachments and, while nobody has handed
 * in, the way of handing in. Class and subject are fixed once it is set.
 */
export default function HomeworkDialog({
    options,
    editing,
    initialSection,
    onClose,
}: {
    options: HomeworkFormOptions;
    editing: HomeworkDetail | null;
    initialSection: string | null;
    onClose: () => void;
}) {
    const firstSection =
        options.sections.find((s) => s.ulid === initialSection) ??
        options.sections[0];
    const [section, setSection] = useState(
        editing?.section_ulid ?? firstSection?.ulid ?? '',
    );
    const subjects =
        options.sections.find((s) => s.ulid === section)?.subjects ?? [];
    const [subject, setSubject] = useState(
        editing?.subject_ulid ?? subjects[0]?.ulid ?? '',
    );
    const [title, setTitle] = useState(editing?.title ?? '');
    const [instructions, setInstructions] = useState(
        editing?.instructions ?? '',
    );
    const [dueOn, setDueOn] = useState(
        editing?.due_on ?? options.suggested_due_on,
    );
    const [type, setType] = useState<SubmissionType>(
        editing?.submission_type ?? 'notebook',
    );
    const [picked, setPicked] = useState<File[]>([]);
    const [removed, setRemoved] = useState<string[]>([]);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);
    const picker = useRef<HTMLInputElement>(null);

    const kept = (editing?.attachments ?? []).filter(
        (file) => !removed.includes(file.ulid),
    );
    const hasHandIns = editing !== null && editing.submitted > 0;

    const chooseSection = (value: string) => {
        setSection(value);
        setSubject(
            options.sections.find((s) => s.ulid === value)?.subjects[0]?.ulid ??
                '',
        );
    };

    const addFiles = async (list: FileList | null) => {
        if (list === null) {
            return;
        }

        const resized = await Promise.all(Array.from(list).map(resizeImage));
        const next = [...picked, ...resized];

        if (next.length + kept.length > options.max_files) {
            setErrors({
                files: `You can attach up to ${options.max_files} files.`,
            });

            return;
        }

        const tooBig = next.find(
            (file) => file.size > options.max_file_mb * 1024 * 1024,
        );

        setErrors(
            tooBig === undefined
                ? {}
                : {
                      files: `${tooBig.name} is larger than ${options.max_file_mb} MB.`,
                  },
        );

        if (tooBig === undefined) {
            setPicked(next);
        }
    };

    const submit = () => {
        const data = {
            title,
            instructions,
            due_on: dueOn,
            submission_type: type,
            files: picked,
            ...(editing === null
                ? { section, subject }
                : { _method: 'put', remove_files: removed }),
        };
        const url = editing === null ? store.url() : update.url(editing.ulid);

        router.post(url, data, {
            forceFormData: true,
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: onClose,
            onError: (found) => setErrors(found),
        });
    };

    const problem = errors.homework ?? errors.section ?? errors.subject;

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[92svh] gap-4 overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>
                        {editing === null
                            ? 'New assignment'
                            : 'Edit assignment'}
                    </DialogTitle>
                    <DialogDescription className="sr-only">
                        {editing === null
                            ? 'Set homework for a class and subject you teach.'
                            : 'Change this homework.'}
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-4 sm:grid-cols-2">
                    <Field id="hw-section" label="Class">
                        <select
                            id="hw-section"
                            className={selectClassName}
                            value={section}
                            disabled={editing !== null}
                            onChange={(event) =>
                                chooseSection(event.target.value)
                            }
                        >
                            {options.sections.map((s) => (
                                <option key={s.ulid} value={s.ulid}>
                                    {s.label}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field id="hw-subject" label="Subject">
                        <select
                            id="hw-subject"
                            className={selectClassName}
                            value={subject}
                            disabled={editing !== null}
                            onChange={(event) => setSubject(event.target.value)}
                        >
                            {(editing === null
                                ? subjects
                                : [
                                      {
                                          ulid: editing.subject_ulid,
                                          name: editing.subject,
                                      },
                                  ]
                            ).map((s) => (
                                <option key={s.ulid} value={s.ulid}>
                                    {s.name}
                                </option>
                            ))}
                        </select>
                    </Field>
                </div>

                <Field id="hw-title" label="Title" error={errors.title}>
                    <Input
                        id="hw-title"
                        value={title}
                        maxLength={options.title_max}
                        placeholder="Fractions — Exercise 7.4, Q1–10"
                        autoFocus
                        onChange={(event) => setTitle(event.target.value)}
                    />
                </Field>

                <Field
                    id="hw-instructions"
                    label="Instructions"
                    error={errors.instructions}
                >
                    <textarea
                        id="hw-instructions"
                        className={textareaClassName}
                        value={instructions}
                        maxLength={options.instructions_max}
                        placeholder="What students should do and how to submit"
                        onChange={(event) =>
                            setInstructions(event.target.value)
                        }
                    />
                </Field>

                <div className="grid gap-4 sm:grid-cols-2">
                    <Field id="hw-due" label="Due date" error={errors.due_on}>
                        <Input
                            id="hw-due"
                            type="date"
                            value={dueOn}
                            min={
                                editing === null
                                    ? options.min_due_on
                                    : undefined
                            }
                            onChange={(event) => setDueOn(event.target.value)}
                        />
                    </Field>
                    <Field
                        id="hw-type"
                        label="Submission"
                        error={errors.submission_type}
                    >
                        <select
                            id="hw-type"
                            className={selectClassName}
                            value={type}
                            disabled={hasHandIns}
                            onChange={(event) =>
                                setType(event.target.value as SubmissionType)
                            }
                        >
                            <option value="notebook">
                                In notebook (checked in class)
                            </option>
                            <option value="upload">
                                Upload in the app (photo or PDF)
                            </option>
                        </select>
                    </Field>
                </div>

                <div className="grid gap-2">
                    <span className="text-sm font-medium">
                        Attachments (optional)
                    </span>
                    {kept.length > 0 && (
                        <div className="grid gap-1.5">
                            <HomeworkFileLinks files={kept} />
                            <div className="flex flex-wrap gap-x-4 gap-y-1">
                                {kept.map((file) => (
                                    <button
                                        key={file.ulid}
                                        type="button"
                                        className="text-xs text-destructive hover:underline"
                                        onClick={() =>
                                            setRemoved([...removed, file.ulid])
                                        }
                                    >
                                        Remove {file.name}
                                    </button>
                                ))}
                            </div>
                        </div>
                    )}
                    <button
                        type="button"
                        onClick={() => picker.current?.click()}
                        className={cn(
                            'flex items-start gap-3 rounded-xl border-2 border-dashed p-4 text-left text-sm hover:bg-muted/50',
                        )}
                    >
                        <Paperclip
                            className="mt-0.5 size-5 shrink-0 text-muted-foreground"
                            aria-hidden
                        />
                        <span>
                            <span className="block font-medium">
                                Attach worksheets, photos or documents
                            </span>
                            <span className="block text-muted-foreground">
                                Up to {options.max_files} files,{' '}
                                {options.max_file_mb} MB each.
                            </span>
                        </span>
                    </button>
                    <input
                        ref={picker}
                        type="file"
                        multiple
                        accept={HOMEWORK_ACCEPT}
                        className="sr-only"
                        tabIndex={-1}
                        onChange={(event) => {
                            void addFiles(event.target.files);
                            event.target.value = '';
                        }}
                    />
                    <PickedFiles
                        files={picked}
                        onRemove={(index) =>
                            setPicked(picked.filter((_, i) => i !== index))
                        }
                    />
                    <InputError message={errors.files} />
                </div>

                {editing === null && (
                    <p className="text-sm text-muted-foreground">
                        Parents are notified. Avoid due dates on holidays.
                    </p>
                )}
                <InputError message={problem} />

                <DialogFooter>
                    <Button type="button" variant="outline" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        onClick={submit}
                        disabled={processing || subject === ''}
                    >
                        {editing === null
                            ? 'Create and notify'
                            : 'Save changes'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
