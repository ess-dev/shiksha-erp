import { useForm } from '@inertiajs/react';
import { FileCheck2, FileClock } from 'lucide-react';
import InputError from '@/components/input-error';
import { selectClassName } from '@/components/school/field';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { formatCalendarDate } from '@/lib/format';
import documents from '@/routes/students/documents';
import type { StudentOptions, StudentProfile } from '@/types/students';

export default function DocumentsTab({
    student,
    options,
}: {
    student: StudentProfile;
    options: StudentOptions;
}) {
    const form = useForm<{ kind: string; file: File | null }>({
        kind: options.document_kinds[0]?.value ?? 'birth_certificate',
        file: null,
    });

    if (student.documents === null) {
        return (
            <Card>
                <CardContent className="py-8 text-center text-sm text-muted-foreground">
                    You do not have access to this student&apos;s documents.
                </CardContent>
            </Card>
        );
    }

    const upload = (event: React.FormEvent) => {
        event.preventDefault();

        form.post(documents.store.url(student.ulid), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => form.reset('file'),
        });
    };

    const verify = (ulid: string) => {
        form.post(documents.verify.url(ulid), { preserveScroll: true });
    };

    return (
        <Card>
            <CardContent className="space-y-4">
                {student.abilities.documents && (
                    <form
                        onSubmit={upload}
                        className="flex flex-wrap items-end gap-3"
                    >
                        <div className="grid gap-1.5">
                            <label
                                htmlFor="document-kind"
                                className="text-xs font-medium text-muted-foreground"
                            >
                                Document type
                            </label>
                            <select
                                id="document-kind"
                                className={`${selectClassName} w-64`}
                                value={form.data.kind}
                                onChange={(event) =>
                                    form.setData('kind', event.target.value)
                                }
                            >
                                {options.document_kinds.map((kind) => (
                                    <option key={kind.value} value={kind.value}>
                                        {kind.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="grid gap-1.5">
                            <label
                                htmlFor="document-file"
                                className="text-xs font-medium text-muted-foreground"
                            >
                                File (PDF, JPEG or PNG, up to 5 MB)
                            </label>
                            <input
                                id="document-file"
                                type="file"
                                accept="application/pdf,image/jpeg,image/png"
                                className="text-sm file:mr-3 file:rounded-md file:border file:bg-background file:px-3 file:py-1.5 file:text-sm"
                                onChange={(event) =>
                                    form.setData(
                                        'file',
                                        event.target.files?.[0] ?? null,
                                    )
                                }
                            />
                        </div>
                        <Button
                            type="submit"
                            disabled={
                                form.processing || form.data.file === null
                            }
                        >
                            {form.processing && <Spinner />}
                            Upload
                        </Button>
                        <div className="basis-full">
                            <InputError message={form.errors.file} />
                            <InputError message={form.errors.kind} />
                            <InputError
                                message={
                                    (form.errors as Record<string, string>)
                                        .student
                                }
                            />
                        </div>
                    </form>
                )}

                {student.documents.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No documents have been uploaded yet.
                    </p>
                ) : (
                    <ul className="divide-y">
                        {student.documents.map((document) => (
                            <li
                                key={document.ulid}
                                className="flex flex-wrap items-center justify-between gap-3 py-3"
                            >
                                <div className="flex items-start gap-3">
                                    {document.is_verified ? (
                                        <FileCheck2 className="mt-0.5 size-5 text-primary" />
                                    ) : (
                                        <FileClock className="mt-0.5 size-5 text-muted-foreground" />
                                    )}
                                    <div>
                                        <div className="text-sm font-medium">
                                            {document.label}{' '}
                                            <Badge
                                                variant={
                                                    document.is_verified
                                                        ? 'default'
                                                        : 'outline'
                                                }
                                            >
                                                {document.is_verified
                                                    ? 'Verified'
                                                    : 'To verify'}
                                            </Badge>
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {document.original_name} ·{' '}
                                            {formatCalendarDate(
                                                document.uploaded_on,
                                            )}
                                            {document.source === 'admission' &&
                                                ' · from admission'}
                                            {document.source === 'parent' &&
                                                ' · sent by parent'}
                                            {document.verified_by &&
                                                ` · verified by ${document.verified_by}`}
                                        </div>
                                    </div>
                                </div>
                                <div className="flex gap-2">
                                    {document.view_url !== null && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            asChild
                                        >
                                            <a
                                                href={document.view_url}
                                                target="_blank"
                                                rel="noreferrer"
                                            >
                                                View
                                            </a>
                                        </Button>
                                    )}
                                    {student.abilities.documents &&
                                        !document.is_verified && (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                disabled={form.processing}
                                                onClick={() =>
                                                    verify(document.ulid)
                                                }
                                            >
                                                Verify
                                            </Button>
                                        )}
                                </div>
                            </li>
                        ))}
                    </ul>
                )}

                <p className="text-xs text-muted-foreground">
                    Children&apos;s documents are visible only to staff with
                    permission to see sensitive student details. Whether parents
                    can see them in their app is a school setting that arrives
                    with the parent app. Retention and deletion periods need the
                    school&apos;s policy and legal review.
                </p>
            </CardContent>
        </Card>
    );
}
