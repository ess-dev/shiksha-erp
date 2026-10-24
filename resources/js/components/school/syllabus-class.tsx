import { router } from '@inertiajs/react';
import { useState } from 'react';
import SyllabusController from '@/actions/App/Http/Controllers/School/SyllabusController';
import InputError from '@/components/input-error';
import Field from '@/components/school/field';
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
import { Spinner } from '@/components/ui/spinner';
import { formatCalendarDate } from '@/lib/format';
import type { SyllabusDetail } from '@/types';

const textareaClassName =
    'w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:opacity-60';

/**
 * Whole class: the textbook, chapter list and assessment every section of the
 * class shares. Saving asks first because it changes what all those parents see.
 */
export default function SyllabusClass({ detail }: { detail: SyllabusDetail }) {
    const saved = detail.class_syllabus;
    const [book, setBook] = useState(saved.book ?? '');
    const [chapters, setChapters] = useState(saved.chapters.join('\n'));
    const [assessment, setAssessment] = useState(saved.assessment ?? '');
    const [confirming, setConfirming] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const readOnly = !detail.can_edit_class;

    const save = () => {
        router.put(
            SyllabusController.updateClass.url({
                section: detail.section_ulid,
                subject: detail.subject_ulid,
            }),
            { book, chapters, assessment },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: () => {
                    setConfirming(false);
                    setErrors({});
                },
                onError: (bag) => {
                    setErrors(bag);
                    setConfirming(false);
                },
            },
        );
    };

    return (
        <div className="grid gap-4 p-4">
            <p className="text-sm text-muted-foreground">
                Changes here apply to{' '}
                <strong className="text-foreground">
                    all {detail.section_count} section
                    {detail.section_count === 1 ? '' : 's'} of{' '}
                    {detail.class_name}
                </strong>{' '}
                on every campus: the textbook, chapter list and how the subject
                is assessed.
            </p>

            {!saved.is_saved && saved.copied_from !== null && (
                <p className="rounded-md border border-primary/30 bg-primary/5 px-3 py-2 text-sm">
                    Started from {saved.copied_from}. Check it and save to use
                    it this year; every section starts with no chapters ticked.
                </p>
            )}

            {readOnly && (
                <p className="rounded-md border bg-muted/40 px-3 py-2 text-sm text-muted-foreground">
                    You can read this, but only a teacher of this class or its
                    coordinator can change it.
                </p>
            )}

            <Field id="syllabus-book" label="Textbook" error={errors.book}>
                <Input
                    id="syllabus-book"
                    maxLength={80}
                    value={book}
                    disabled={readOnly}
                    onChange={(event) => setBook(event.target.value)}
                />
            </Field>

            <Field
                id="syllabus-chapters"
                label="Chapters, one per line"
                error={errors.chapters}
            >
                <textarea
                    id="syllabus-chapters"
                    rows={10}
                    value={chapters}
                    disabled={readOnly}
                    onChange={(event) => setChapters(event.target.value)}
                    className={textareaClassName}
                />
                <p className="text-xs text-muted-foreground">
                    Changing the order or names of chapters clears the ticks of
                    the chapters that moved, in every section.
                </p>
            </Field>

            <Field
                id="syllabus-assessment"
                label="Assessment"
                error={errors.assessment}
            >
                <Input
                    id="syllabus-assessment"
                    maxLength={120}
                    value={assessment}
                    disabled={readOnly}
                    placeholder="Periodic tests 20% · Half-yearly 30% · Annual 50%"
                    onChange={(event) => setAssessment(event.target.value)}
                />
            </Field>

            <InputError message={errors.syllabus} />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <p className="text-xs text-muted-foreground">
                    {saved.updated_on !== null &&
                        `Last updated ${formatCalendarDate(saved.updated_on)}${saved.updated_by === null ? '' : ` by ${saved.updated_by}`}`}
                </p>
                {!readOnly && (
                    <Button type="button" onClick={() => setConfirming(true)}>
                        Save for {detail.class_name}
                    </Button>
                )}
            </div>

            <Dialog
                open={confirming}
                onOpenChange={(open) => !processing && setConfirming(open)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            Update {detail.class_name} {detail.subject_name}?
                        </DialogTitle>
                        <DialogDescription>
                            Every section of {detail.class_name} sees the new
                            textbook and chapter list.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={processing}
                            onClick={() => setConfirming(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            disabled={processing}
                            onClick={save}
                        >
                            {processing && <Spinner />}
                            Save for all sections
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
