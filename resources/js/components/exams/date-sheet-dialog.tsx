import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatCalendarDay } from '@/lib/format';
import type { ExamRow } from '@/types';

function csvCell(value: string): string {
    const safe = /^[=+\-@]/.test(value) ? `'${value}` : value;

    return `"${safe.replace(/"/g, '""')}"`;
}

function downloadCsv(exam: ExamRow) {
    const lines = [
        ['Date', 'Subject', 'Time'],
        ...exam.date_sheet.map((row) => [
            row.held_on,
            row.subject_name,
            row.begins_at ?? '',
        ]),
    ].map((line) => line.map(csvCell).join(','));

    const url = URL.createObjectURL(
        new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8' }),
    );
    const link = document.createElement('a');
    link.href = url;
    link.download = `${exam.name.replace(/\W+/g, '-').toLowerCase()}-date-sheet.csv`;
    link.click();
    URL.revokeObjectURL(url);
}

export default function DateSheetDialog({
    exam,
    onClose,
}: {
    exam: ExamRow;
    onClose: () => void;
}) {
    const dates =
        exam.ends_on === exam.starts_on
            ? formatCalendarDay(exam.starts_on)
            : `${formatCalendarDay(exam.starts_on)} – ${formatCalendarDay(exam.ends_on)}`;

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{exam.name}: date sheet</DialogTitle>
                    <DialogDescription>
                        {exam.classes_label} · out of {exam.max_marks} · {dates}
                    </DialogDescription>
                </DialogHeader>

                {exam.date_sheet.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No date sheet. Every subject is tested within the exam
                        dates.
                    </p>
                ) : (
                    <table className="w-full text-sm">
                        <thead className="text-left text-xs tracking-wide text-muted-foreground uppercase">
                            <tr>
                                <th scope="col" className="py-2">
                                    Date
                                </th>
                                <th scope="col" className="py-2">
                                    Subject
                                </th>
                                <th scope="col" className="py-2">
                                    Time
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {exam.date_sheet.map((row) => (
                                <tr key={row.subject_ulid}>
                                    <td className="py-2">
                                        {formatCalendarDay(row.held_on)}
                                    </td>
                                    <td className="py-2 font-semibold">
                                        {row.subject_name}
                                    </td>
                                    <td className="py-2">
                                        {row.begins_at ?? '—'}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}

                {exam.instructions !== null && (
                    <p className="text-sm">{exam.instructions}</p>
                )}

                <DialogFooter>
                    <Button type="button" variant="outline" onClick={onClose}>
                        Close
                    </Button>
                    {exam.date_sheet.length > 0 && (
                        <Button type="button" onClick={() => downloadCsv(exam)}>
                            Download CSV
                        </Button>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
