import InitialsBadge from '@/components/students/initials-badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatCalendarDate } from '@/lib/format';

export type RegisterSummary = {
    sectionLabel: string;
    date: string;
    isCorrection: boolean;
    present: number;
    absent: number;
    late: number;
    absentees: { ulid: string; name: string; roll: number | null }[];
};

function Cell({
    label,
    value,
    className,
}: {
    label: string;
    value: number;
    className: string;
}) {
    return (
        <div className="flex flex-col items-center gap-1 rounded-md border p-3">
            <span className="text-xs text-muted-foreground">{label}</span>
            <strong className={`text-xl ${className}`}>{value}</strong>
        </div>
    );
}

/**
 * Shown after a save: the counts, who is absent, and an honest note that
 * guardian alerts are not available yet.
 */
export default function SummaryDialog({
    summary,
    onClose,
}: {
    summary: RegisterSummary;
    onClose: () => void;
}) {
    const total = summary.present + summary.absent + summary.late;
    const percent =
        total === 0
            ? '—'
            : `${(((summary.present + summary.late) / total) * 100).toFixed(1)}%`;

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        {summary.isCorrection
                            ? 'Attendance updated'
                            : 'Attendance saved'}
                    </DialogTitle>
                    <DialogDescription>
                        {summary.sectionLabel} ·{' '}
                        {formatCalendarDate(summary.date)}
                    </DialogDescription>
                </DialogHeader>

                <div className="grid grid-cols-3 gap-2">
                    <Cell
                        label="Present"
                        value={summary.present}
                        className="text-green-700"
                    />
                    <Cell
                        label="Absent"
                        value={summary.absent}
                        className="text-destructive"
                    />
                    <Cell
                        label="Late"
                        value={summary.late}
                        className="text-amber-600"
                    />
                </div>
                <p className="text-sm">
                    <strong>{percent}</strong> attendance (late counts as
                    present).
                </p>

                {summary.absentees.length > 0 && (
                    <div>
                        <h3 className="mb-2 text-sm font-medium">Absent</h3>
                        <ul className="divide-y rounded-md border">
                            {summary.absentees.map((student) => (
                                <li
                                    key={student.ulid}
                                    className="flex items-center gap-3 px-3 py-2 text-sm"
                                >
                                    <InitialsBadge
                                        name={student.name}
                                        className="size-7"
                                    />
                                    <span className="grow">{student.name}</span>
                                    {student.roll !== null && (
                                        <span className="text-xs text-muted-foreground">
                                            Roll {student.roll}
                                        </span>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </div>
                )}

                <p className="text-xs text-muted-foreground">
                    Guardian absence alerts: not available yet. Messaging is not
                    set up, so nobody was contacted.
                </p>

                <DialogFooter>
                    <Button type="button" onClick={onClose}>
                        Done
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
