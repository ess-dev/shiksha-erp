import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import Field, { selectClassName } from '@/components/school/field';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { formatCalendarDate } from '@/lib/format';
import {
    decision as decisionRoute,
    details as detailsRoute,
    notes as notesRoute,
} from '@/routes/admissions';
import documents from '@/routes/admissions/documents';
import type {
    AdmissionAbilities,
    AdmissionDetail,
    AdmissionDocumentRow,
    AdmissionOptions,
} from '@/types/admissions';

const textareaClassName =
    'min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';

function Fact({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="text-sm font-medium">{value}</dd>
        </div>
    );
}

function DocumentRow({
    application,
    row,
    canTick,
    canUpload,
    onError,
}: {
    application: AdmissionDetail;
    row: AdmissionDocumentRow;
    canTick: boolean;
    canUpload: boolean;
    onError: (message: string) => void;
}) {
    const routeArgs = { application: application.ulid, kind: row.kind };

    return (
        <li className="flex flex-wrap items-center gap-3 py-2">
            <label className="flex min-w-0 grow items-center gap-3 text-sm">
                <Checkbox
                    checked={row.is_received}
                    disabled={!canTick}
                    onCheckedChange={(checked) =>
                        router.put(
                            documents.received.url(routeArgs),
                            { received: checked === true },
                            { preserveScroll: true },
                        )
                    }
                />
                <span className="min-w-0">
                    {row.label}
                    {row.file_name !== null && (
                        <span className="block truncate text-xs text-muted-foreground">
                            {row.file_name}
                        </span>
                    )}
                </span>
            </label>
            {row.view_url !== null && (
                <Button variant="ghost" size="sm" asChild>
                    <a href={row.view_url} target="_blank" rel="noreferrer">
                        View
                    </a>
                </Button>
            )}
            {canUpload && (
                <label className="inline-flex h-8 cursor-pointer items-center rounded-md border px-3 text-sm font-medium shadow-xs focus-within:ring-[3px] focus-within:ring-ring/50 hover:bg-accent">
                    {row.file_name !== null ? 'Replace' : 'Upload'}
                    <input
                        type="file"
                        className="sr-only"
                        accept="application/pdf,image/jpeg,image/png"
                        aria-label={`${row.file_name !== null ? 'Replace' : 'Upload'} ${row.label}`}
                        onChange={(event) => {
                            const file = event.target.files?.[0];

                            event.target.value = '';

                            if (file === undefined) {
                                return;
                            }

                            router.post(
                                documents.store.url(routeArgs),
                                { file },
                                {
                                    forceFormData: true,
                                    preserveScroll: true,
                                    onError: (errors) =>
                                        onError(
                                            errors.file ??
                                                errors.application ??
                                                'The document could not be uploaded.',
                                        ),
                                },
                            );
                        }}
                    />
                </label>
            )}
            <Badge variant={row.is_received ? 'default' : 'secondary'}>
                {row.is_received ? 'Received' : 'Pending'}
            </Badge>
        </li>
    );
}

function DetailsForm({
    application,
    options,
}: {
    application: AdmissionDetail;
    options: AdmissionOptions;
}) {
    const form = useForm({
        born_on: application.born_on ?? '',
        blood_group: application.blood_group ?? '',
        house: application.house_ulid ?? '',
        address: application.address ?? '',
        email: application.email ?? '',
    });

    const save = (event: React.FormEvent) => {
        event.preventDefault();

        form.put(detailsRoute.url(application.ulid), { preserveScroll: true });
    };

    return (
        <form onSubmit={save} className="grid gap-3 sm:grid-cols-2" noValidate>
            <Field
                id="app-dob"
                label="Date of birth"
                error={form.errors.born_on}
            >
                <Input
                    id="app-dob"
                    type="date"
                    value={form.data.born_on}
                    onChange={(e) => form.setData('born_on', e.target.value)}
                />
            </Field>
            <Field
                id="app-blood"
                label="Blood group"
                error={form.errors.blood_group}
            >
                <select
                    id="app-blood"
                    className={selectClassName}
                    value={form.data.blood_group}
                    onChange={(e) =>
                        form.setData('blood_group', e.target.value)
                    }
                >
                    <option value="">Choose…</option>
                    {options.blood_groups.map((group) => (
                        <option key={group.value} value={group.value}>
                            {group.label}
                        </option>
                    ))}
                </select>
            </Field>
            <Field id="app-house" label="House" error={form.errors.house}>
                <select
                    id="app-house"
                    className={selectClassName}
                    value={form.data.house}
                    onChange={(e) => form.setData('house', e.target.value)}
                >
                    <option value="">Not assigned</option>
                    {options.houses.map((house) => (
                        <option key={house.value} value={house.value}>
                            {house.label}
                        </option>
                    ))}
                </select>
            </Field>
            <Field
                id="app-email"
                label="Parent email"
                error={form.errors.email}
            >
                <Input
                    id="app-email"
                    type="email"
                    autoComplete="off"
                    value={form.data.email}
                    onChange={(e) => form.setData('email', e.target.value)}
                />
            </Field>
            <div className="sm:col-span-2">
                <Field
                    id="app-address"
                    label="Address"
                    error={form.errors.address}
                >
                    <textarea
                        id="app-address"
                        className={textareaClassName}
                        value={form.data.address}
                        onChange={(e) =>
                            form.setData('address', e.target.value)
                        }
                    />
                </Field>
            </div>
            <div className="flex items-center gap-3 sm:col-span-2">
                <Button type="submit" size="sm" disabled={form.processing}>
                    {form.processing && <Spinner />}
                    Save details
                </Button>
                {!application.details_complete && (
                    <span className="text-xs text-muted-foreground">
                        Date of birth, blood group and address are needed before
                        Documents verified.
                    </span>
                )}
            </div>
        </form>
    );
}

export default function ApplicationDialog({
    application,
    options,
    abilities,
    onClose,
    onMove,
    onEnrol,
}: {
    application: AdmissionDetail;
    options: AdmissionOptions;
    abilities: AdmissionAbilities;
    onClose: () => void;
    onMove: (stage: string) => void;
    onEnrol: () => void;
}) {
    const [notes, setNotes] = useState(application.notes ?? '');
    const [error, setError] = useState<string | null>(null);

    const editable = abilities.manage && !application.is_locked;
    const canDecide =
        editable && application.stage === 'decision' && !application.is_closed;

    const saveNotes = () => {
        if (notes !== (application.notes ?? '')) {
            router.put(
                notesRoute.url(application.ulid),
                { notes },
                { preserveScroll: true },
            );
        }
    };

    const close = () => {
        saveNotes();
        onClose();
    };

    const decide = (decision: string) => {
        saveNotes();
        router.post(
            decisionRoute.url(application.ulid),
            { decision },
            {
                preserveScroll: true,
                onError: (errors) =>
                    setError(errors.application ?? 'That could not be saved.'),
            },
        );
    };

    return (
        <Dialog open onOpenChange={(open) => !open && close()}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>{application.name}</DialogTitle>
                </DialogHeader>

                <div className="flex flex-wrap gap-2">
                    <Badge variant="secondary">{application.stage_label}</Badge>
                    {application.decision_label !== null && (
                        <Badge
                            variant={
                                application.is_closed
                                    ? 'destructive'
                                    : 'outline'
                            }
                        >
                            {application.decision_label}
                        </Badge>
                    )}
                </div>

                <dl className="grid gap-x-6 gap-y-3 sm:grid-cols-2">
                    <Fact
                        label="Applying for"
                        value={`${application.class_name}, ${application.campus_name}`}
                    />
                    <Fact label="Gender" value={application.gender_label} />
                    {!editable && (
                        <Fact
                            label="Date of birth"
                            value={
                                application.born_on === null
                                    ? 'Not given'
                                    : formatCalendarDate(application.born_on)
                            }
                        />
                    )}
                    <Fact
                        label="Parent"
                        value={`${application.parent_name} (${application.parent_relation})`}
                    />
                    <Fact label="Phone" value={application.phone} />
                    <Fact label="Source" value={application.source_label} />
                    <Fact
                        label="Enquiry on"
                        value={formatCalendarDate(application.enquired_on)}
                    />
                    {!editable && (
                        <>
                            <Fact
                                label="Blood group"
                                value={application.blood_group ?? 'Not given'}
                            />
                            <Fact
                                label="Address"
                                value={application.address ?? 'Not given'}
                            />
                        </>
                    )}
                </dl>

                {editable && (
                    <section className="grid gap-2">
                        <h3 className="text-sm font-semibold">
                            Application details
                        </h3>
                        <DetailsForm
                            key={`${application.ulid}-${application.born_on}-${application.blood_group}-${application.address}-${application.house_ulid}-${application.email}`}
                            application={application}
                            options={options}
                        />
                    </section>
                )}

                <section>
                    <h3 className="text-sm font-semibold">Documents</h3>
                    {application.documents.length === 0 ? (
                        <p className="py-2 text-sm text-muted-foreground">
                            No documents are required for this class.
                        </p>
                    ) : (
                        <ul className="divide-y">
                            {application.documents.map((row) => (
                                <DocumentRow
                                    key={row.kind}
                                    application={application}
                                    row={row}
                                    canTick={editable}
                                    canUpload={editable && abilities.documents}
                                    onError={setError}
                                />
                            ))}
                        </ul>
                    )}
                    <p className="mt-2 text-xs text-muted-foreground">
                        Uploaded copies move to the student’s record on
                        enrolment, where staff with access can view them.
                    </p>
                </section>

                {canDecide && (
                    <section className="grid gap-2">
                        <h3 className="text-sm font-semibold">Decision</h3>
                        <div className="flex flex-wrap gap-2">
                            <Button
                                type="button"
                                size="sm"
                                variant={
                                    application.decision === 'accepted'
                                        ? 'default'
                                        : 'outline'
                                }
                                onClick={() => decide('accepted')}
                            >
                                Accept
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                variant={
                                    application.decision === 'waitlisted'
                                        ? 'default'
                                        : 'outline'
                                }
                                onClick={() => decide('waitlisted')}
                            >
                                Waitlist
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                onClick={() => decide('rejected')}
                            >
                                Not admitted
                            </Button>
                        </div>
                    </section>
                )}

                <section className="grid gap-2">
                    <label
                        htmlFor="app-notes"
                        className="text-sm font-semibold"
                    >
                        Notes
                    </label>
                    <textarea
                        id="app-notes"
                        className={textareaClassName}
                        placeholder="Interaction notes, interview outcome"
                        value={notes}
                        readOnly={!abilities.manage}
                        onChange={(e) => setNotes(e.target.value)}
                        onBlur={saveNotes}
                    />
                </section>

                <section>
                    <h3 className="text-sm font-semibold">History</h3>
                    <ul className="divide-y text-sm">
                        {application.history.map((row, index) => (
                            <li key={index} className="flex gap-4 py-1.5">
                                <span className="w-20 shrink-0 text-xs text-muted-foreground">
                                    {formatCalendarDate(row.happened_on).slice(
                                        0,
                                        6,
                                    )}
                                </span>
                                <span>
                                    {row.label}
                                    {row.note !== null && ` · ${row.note}`}
                                </span>
                            </li>
                        ))}
                    </ul>
                </section>

                <InputError message={error ?? undefined} />

                <DialogFooter>
                    <Button type="button" variant="outline" onClick={close}>
                        Close
                    </Button>
                    {application.student_ulid === null &&
                        abilities.enroll &&
                        application.stage === 'decision' &&
                        application.decision === 'accepted' &&
                        !application.is_locked && (
                            <Button type="button" onClick={onEnrol}>
                                Enroll student
                            </Button>
                        )}
                    {editable && application.next_stage !== null && (
                        <Button
                            type="button"
                            onClick={() => {
                                saveNotes();
                                onMove(application.next_stage as string);
                            }}
                        >
                            Move to {application.next_stage_label}
                        </Button>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
