import { router } from '@inertiajs/react';
import { Download, FileSpreadsheet } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { requestJson } from '@/lib/http';
import importRoutes from '@/routes/students/import';
import type { ImportPreview, ImportRow } from '@/types/students';

const MAX_BYTES = 2 * 1024 * 1024;

/** Keeps a spreadsheet from running a cell that starts like a formula. */
function csvCell(value: string): string {
    const safe = /^[=+\-@\t\r]/.test(value) ? `'${value}` : value;

    return /[",\n]/.test(safe) ? `"${safe.replace(/"/g, '""')}"` : safe;
}

function downloadRowsToFix(rows: ImportRow[]): void {
    const lines = [['Row', 'Student', 'Class', 'Problems']]
        .concat(
            rows
                .filter((row) => row.status === 'fix')
                .map((row) => [
                    String(row.line),
                    row.student_name,
                    row.class_label ?? '',
                    row.errors.join('; '),
                ]),
        )
        .map((line) => line.map(csvCell).join(','));

    const url = URL.createObjectURL(
        new Blob([`﻿${lines.join('\n')}`], { type: 'text/csv' }),
    );
    const link = document.createElement('a');

    link.href = url;
    link.download = 'rows-to-fix.csv';
    link.click();
    URL.revokeObjectURL(url);
}

function RowCheck({ row }: { row: ImportRow }) {
    if (row.status === 'fix') {
        return (
            <>
                <Badge variant="destructive">Fix</Badge>
                <ul className="mt-1 space-y-0.5 text-xs text-red-600 dark:text-red-400">
                    {row.errors.map((message) => (
                        <li key={message}>{message}</li>
                    ))}
                </ul>
            </>
        );
    }

    return (
        <>
            <Badge variant={row.status === 'warning' ? 'outline' : 'default'}>
                Ready
            </Badge>
            {row.warnings.length > 0 && (
                <ul className="mt-1 space-y-0.5 text-xs text-muted-foreground">
                    {row.warnings.map((message) => (
                        <li key={message}>{message}</li>
                    ))}
                </ul>
            )}
        </>
    );
}

export default function ImportDialog({ onClose }: { onClose: () => void }) {
    const [file, setFile] = useState<File | null>(null);
    const [preview, setPreview] = useState<ImportPreview | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [checking, setChecking] = useState(false);
    const [importing, setImporting] = useState(false);

    const choose = async (chosen: File | undefined) => {
        if (chosen === undefined) {
            return;
        }

        setError(null);

        if (!/\.csv$/i.test(chosen.name)) {
            setError('Choose a .csv file. In Excel: File, Save as, CSV UTF-8.');

            return;
        }

        if (chosen.size > MAX_BYTES) {
            setError('That file is over 2 MB.');

            return;
        }

        const body = new FormData();
        body.append('file', chosen);
        setChecking(true);

        const result = await requestJson<ImportPreview>(
            importRoutes.preview.url(),
            { method: 'POST', body },
        );

        setChecking(false);

        if (!result.ok || result.data === null) {
            setError(
                result.errors.file ??
                    'The file could not be checked. Try again.',
            );

            return;
        }

        setFile(chosen);
        setPreview(result.data);
    };

    const runImport = () => {
        if (file === null) {
            return;
        }

        router.post(
            importRoutes.store.url(),
            { file },
            {
                forceFormData: true,
                onStart: () => setImporting(true),
                onFinish: () => setImporting(false),
                onSuccess: onClose,
            },
        );
    };

    const ready = preview?.ready ?? 0;

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="flex max-h-[90vh] flex-col gap-0 p-0 sm:max-w-3xl">
                <DialogHeader className="border-b px-6 py-4">
                    <DialogTitle>
                        Import students from a spreadsheet
                    </DialogTitle>
                    <DialogDescription className="sr-only">
                        Upload a CSV file, check it, then import the rows that
                        are ready
                    </DialogDescription>
                </DialogHeader>

                <div className="min-h-0 flex-1 space-y-4 overflow-y-auto px-6 py-4">
                    {preview === null ? (
                        <>
                            <ol className="list-decimal space-y-1 pl-5 text-sm">
                                <li>
                                    Download the template and fill one row per
                                    student (Excel or Google Sheets).
                                </li>
                                <li>
                                    Save it as <strong>CSV</strong> and choose
                                    it below. A guardian whose mobile is already
                                    on file is linked, so siblings join their
                                    family automatically.
                                </li>
                                <li>
                                    Check the preview, then import the rows that
                                    are ready.
                                </li>
                            </ol>

                            <Button variant="outline" size="sm" asChild>
                                <a href={importRoutes.template.url()} download>
                                    <Download /> Download template
                                </a>
                            </Button>

                            <label className="flex cursor-pointer flex-col items-center gap-1 rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground hover:bg-muted/30">
                                {checking ? (
                                    <Spinner />
                                ) : (
                                    <FileSpreadsheet className="size-6" />
                                )}
                                <span className="font-medium text-foreground">
                                    Choose a CSV file
                                </span>
                                <span className="text-xs">
                                    Required columns: first name, last name,
                                    gender, date of birth, blood group, house
                                    (or Not assigned), address, class, section,
                                    guardian name, mobile. Up to 1,000 students
                                    and 2 MB.
                                </span>
                                <input
                                    type="file"
                                    accept=".csv,text/csv"
                                    className="sr-only"
                                    disabled={checking}
                                    onChange={(event) =>
                                        void choose(event.target.files?.[0])
                                    }
                                />
                            </label>
                            {error !== null && (
                                <p className="text-sm text-red-600 dark:text-red-400">
                                    {error}
                                </p>
                            )}
                        </>
                    ) : (
                        <>
                            {preview.missing_columns.length > 0 && (
                                <p className="rounded-md border border-yellow-500/40 bg-yellow-500/10 p-3 text-sm">
                                    Missing columns:{' '}
                                    {preview.missing_columns.join(', ')}. Use
                                    the template headings.
                                </p>
                            )}

                            <dl className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                <div className="rounded-md border p-3">
                                    <dt className="text-xs text-muted-foreground">
                                        File
                                    </dt>
                                    <dd className="truncate text-sm font-medium">
                                        {preview.file_name}
                                    </dd>
                                </div>
                                <div className="rounded-md border p-3">
                                    <dt className="text-xs text-muted-foreground">
                                        Ready to import
                                    </dt>
                                    <dd className="text-lg font-semibold">
                                        {preview.ready}
                                    </dd>
                                </div>
                                <div className="rounded-md border p-3">
                                    <dt className="text-xs text-muted-foreground">
                                        With warnings
                                    </dt>
                                    <dd className="text-lg font-semibold">
                                        {preview.with_warnings}
                                    </dd>
                                </div>
                                <div className="rounded-md border p-3">
                                    <dt className="text-xs text-muted-foreground">
                                        Need fixing
                                    </dt>
                                    <dd className="text-lg font-semibold text-red-600 dark:text-red-400">
                                        {preview.need_fixing}
                                    </dd>
                                </div>
                            </dl>

                            <div className="max-h-80 overflow-auto rounded-md border">
                                <table className="w-full text-left text-sm">
                                    <thead className="sticky top-0 border-b bg-muted text-xs text-muted-foreground uppercase">
                                        <tr>
                                            <th className="px-3 py-2">Row</th>
                                            <th className="px-3 py-2">
                                                Student
                                            </th>
                                            <th className="px-3 py-2">Class</th>
                                            <th className="hidden px-3 py-2 sm:table-cell">
                                                Guardian
                                            </th>
                                            <th className="px-3 py-2">Check</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {preview.rows.map((row) => (
                                            <tr
                                                key={row.line}
                                                className="align-top"
                                            >
                                                <td className="px-3 py-2 text-muted-foreground">
                                                    {row.line}
                                                </td>
                                                <td className="px-3 py-2">
                                                    <strong>
                                                        {row.student_name}
                                                    </strong>
                                                    <div className="text-xs text-muted-foreground">
                                                        {row.details}
                                                    </div>
                                                </td>
                                                <td className="px-3 py-2 whitespace-nowrap">
                                                    {row.class_label ?? '—'}
                                                </td>
                                                <td className="hidden px-3 py-2 sm:table-cell">
                                                    {row.guardian}
                                                </td>
                                                <td className="px-3 py-2">
                                                    <RowCheck row={row} />
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            <p className="text-xs text-muted-foreground">
                                Billing fees and the admission fee on import are
                                not available yet: they arrive with the Fees
                                module. Guardians are invited to the parent app
                                once it is available.
                            </p>
                        </>
                    )}
                </div>

                <DialogFooter className="border-t px-6 py-3 sm:justify-between">
                    {preview === null ? (
                        <Button variant="outline" onClick={onClose}>
                            Cancel
                        </Button>
                    ) : (
                        <>
                            <div className="flex gap-2">
                                <Button
                                    variant="outline"
                                    onClick={() => {
                                        setPreview(null);
                                        setFile(null);
                                    }}
                                >
                                    Choose another file
                                </Button>
                                {preview.need_fixing > 0 && (
                                    <Button
                                        variant="ghost"
                                        onClick={() =>
                                            downloadRowsToFix(preview.rows)
                                        }
                                    >
                                        <Download /> Rows to fix (CSV)
                                    </Button>
                                )}
                            </div>
                            <Button
                                disabled={
                                    ready === 0 ||
                                    preview.missing_columns.length > 0 ||
                                    importing
                                }
                                onClick={runImport}
                            >
                                {importing && <Spinner />}
                                Import {ready}{' '}
                                {ready === 1 ? 'student' : 'students'}
                            </Button>
                        </>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
