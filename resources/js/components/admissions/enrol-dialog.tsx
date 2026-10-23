import { Link, useForm } from '@inertiajs/react';
import { Check } from 'lucide-react';
import InputError from '@/components/input-error';
import { selectClassName } from '@/components/school/field';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { enrol } from '@/routes/admissions';
import { show as showStudent } from '@/routes/students';
import type { AdmissionDetail, EnrolledApplicant } from '@/types/admissions';
import type { SectionOption } from '@/types/students';

export function EnrolDialog({
    application,
    sections,
    onClose,
}: {
    application: AdmissionDetail;
    sections: SectionOption[];
    onClose: () => void;
}) {
    const form = useForm({
        section: sections.find((section) => !section.is_full)?.ulid ?? '',
    });

    const errors = form.errors as Record<string, string>;

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        form.post(enrol.url(application.ulid), {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Enroll {application.name}</DialogTitle>
                    <DialogDescription>
                        Choose the section. The admission and roll numbers are
                        given automatically.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="grid gap-4" noValidate>
                    <div className="grid gap-1.5">
                        <Label htmlFor="enrol-section">Section</Label>
                        <select
                            id="enrol-section"
                            className={selectClassName}
                            value={form.data.section}
                            onChange={(e) =>
                                form.setData('section', e.target.value)
                            }
                        >
                            {sections.map((section) => (
                                <option
                                    key={section.ulid}
                                    value={section.ulid}
                                    disabled={section.is_full}
                                >
                                    {section.label} · {section.campus_name} ·{' '}
                                    {section.enrolled}
                                    {section.capacity !== null
                                        ? `/${section.capacity}`
                                        : ''}{' '}
                                    seats{section.is_full ? ' (full)' : ''}
                                </option>
                            ))}
                        </select>
                        {sections.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                There is no section of {application.class_name}{' '}
                                that you can enrol into.
                            </p>
                        )}
                        <InputError message={form.errors.section} />
                    </div>

                    <div>
                        <h3 className="mb-2 text-sm font-semibold">
                            What gets created
                        </h3>
                        <ul className="grid gap-1.5 text-sm">
                            <li className="flex items-start gap-2">
                                <Check className="mt-0.5 size-4 text-primary" />
                                Student record with an admission number and roll
                                number
                            </li>
                            <li className="flex items-start gap-2">
                                <Check className="mt-0.5 size-4 text-primary" />
                                Guardian {application.parent_name} with parent
                                app access
                            </li>
                            <li className="flex items-start gap-2">
                                <Check className="mt-0.5 size-4 text-primary" />
                                The documents collected so far, on the student’s
                                record
                            </li>
                        </ul>
                        <p className="mt-3 text-xs text-muted-foreground">
                            The fee schedule is created once Fees is available.
                        </p>
                    </div>

                    <InputError
                        message={errors.application ?? errors.student}
                    />

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            disabled={
                                form.processing || form.data.section === ''
                            }
                        >
                            {form.processing && <Spinner />}
                            Enroll
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export function EnrolledDialog({
    enrolled,
    onClose,
}: {
    enrolled: EnrolledApplicant;
    onClose: () => void;
}) {
    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle>Enrolled</DialogTitle>
                </DialogHeader>
                <div className="grid justify-items-center gap-2 py-2 text-center">
                    <span className="flex size-12 items-center justify-center rounded-full bg-primary/10 text-primary">
                        <Check />
                    </span>
                    <p className="font-semibold">
                        {enrolled.name} is now in {enrolled.section_label}
                    </p>
                    <p className="text-sm text-muted-foreground">
                        Admission no. {enrolled.admission_number} · roll{' '}
                        {enrolled.roll_number}. They appear in Students and the
                        class register.
                    </p>
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" onClick={onClose}>
                        Back to board
                    </Button>
                    <Button asChild>
                        <Link href={showStudent(enrolled.student_ulid)}>
                            Open profile
                        </Link>
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
