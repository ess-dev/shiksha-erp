import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Plus, RotateCcw, Trash2 } from 'lucide-react';
import { useState } from 'react';
import ConfirmDialog from '@/components/attendance/confirm-dialog';
import GradePill from '@/components/exams/grade-pill';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { grading, index } from '@/routes/exams';
import { approval } from '@/routes/exams/grading';
import gradingRoutes from '@/routes/exams/grading';
import type { GradeBand, GradeScaleScreen } from '@/types';

type Row = { grade: string; min_percent: number; grade_point: number };

const MAX_GRADES = 12;

function toRows(bands: GradeBand[]): Row[] {
    return bands.map((band) => ({
        grade: band.grade,
        min_percent: band.min_percent,
        grade_point: band.grade_point,
    }));
}

/**
 * The school's grading scale: grades from the highest down, each with the
 * percentage it starts at and its grade point. The server checks the order
 * and saves it; exams with published marks keep the scale they used.
 */
export default function Grading({ screen }: { screen: GradeScaleScreen }) {
    const form = useForm<{ grades: Row[] }>({
        grades: toRows(screen.grades),
    });
    const [confirmReset, setConfirmReset] = useState(false);
    const rows = form.data.grades;
    const errors = form.errors as Record<string, string>;
    const firstRowError = Object.entries(errors).find(([key]) =>
        key.startsWith('grades.'),
    )?.[1];

    const setRow = (position: number, patch: Partial<Row>) =>
        form.setData(
            'grades',
            rows.map((row, at) =>
                at === position ? { ...row, ...patch } : row,
            ),
        );

    const numberFrom = (text: string): number =>
        text === '' ? Number.NaN : Number(text);

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.put(gradingRoutes.update.url(), { preserveScroll: true });
    };

    return (
        <>
            <Head title="Grading scale" />

            <div className="mx-auto flex w-full max-w-3xl flex-col gap-4 p-4">
                <div>
                    <Button asChild variant="link" size="sm" className="-ml-3">
                        <Link href={index()}>
                            <ArrowLeft aria-hidden /> Back to exams
                        </Link>
                    </Button>
                    <h1 className="text-xl font-semibold tracking-tight">
                        Grading scale
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Grades teachers see while entering marks and parents see
                        on results and report cards.
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Grades</CardTitle>
                        <CardDescription>
                            {screen.is_saved
                                ? 'Your school’s own scale.'
                                : 'A CBSE-style starting scale (illustrative). Check it against current board guidance before you rely on it.'}{' '}
                            Highest grade first; the last grade starts at 0% and
                            counts as below the pass mark.
                            {screen.frozen_exam_count > 0 &&
                                ` ${screen.frozen_exam_count} ${screen.frozen_exam_count === 1 ? 'exam keeps' : 'exams keep'} the scale its marks were published with.`}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="flex flex-col gap-4">
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="text-left text-xs text-muted-foreground">
                                        <tr>
                                            <th
                                                scope="col"
                                                className="py-2 pr-2"
                                            >
                                                Grade
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-2 py-2"
                                            >
                                                Starts at (%)
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-2 py-2"
                                            >
                                                Up to
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-2 py-2"
                                            >
                                                Grade point
                                            </th>
                                            <th scope="col" className="py-2">
                                                <span className="sr-only">
                                                    Remove
                                                </span>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {rows.map((row, position) => {
                                            const upper =
                                                position === 0
                                                    ? 100
                                                    : (rows[position - 1]
                                                          ?.min_percent ?? 1) -
                                                      1;

                                            return (
                                                <tr key={position}>
                                                    <td className="py-1.5 pr-2">
                                                        <Input
                                                            aria-label={`Grade ${position + 1} name`}
                                                            value={row.grade}
                                                            maxLength={4}
                                                            className="w-20"
                                                            onChange={(event) =>
                                                                setRow(
                                                                    position,
                                                                    {
                                                                        grade: event
                                                                            .target
                                                                            .value,
                                                                    },
                                                                )
                                                            }
                                                        />
                                                    </td>
                                                    <td className="px-2 py-1.5">
                                                        <Input
                                                            aria-label={`Grade ${row.grade || position + 1} starts at percent`}
                                                            type="number"
                                                            min={0}
                                                            max={100}
                                                            value={
                                                                Number.isNaN(
                                                                    row.min_percent,
                                                                )
                                                                    ? ''
                                                                    : row.min_percent
                                                            }
                                                            className="w-24"
                                                            onChange={(event) =>
                                                                setRow(
                                                                    position,
                                                                    {
                                                                        min_percent:
                                                                            numberFrom(
                                                                                event
                                                                                    .target
                                                                                    .value,
                                                                            ),
                                                                    },
                                                                )
                                                            }
                                                        />
                                                    </td>
                                                    <td className="px-2 py-1.5 text-muted-foreground tabular-nums">
                                                        {Number.isNaN(upper)
                                                            ? '—'
                                                            : `${upper}%`}
                                                    </td>
                                                    <td className="px-2 py-1.5">
                                                        <Input
                                                            aria-label={`Grade ${row.grade || position + 1} grade point`}
                                                            type="number"
                                                            min={0}
                                                            max={100}
                                                            value={
                                                                Number.isNaN(
                                                                    row.grade_point,
                                                                )
                                                                    ? ''
                                                                    : row.grade_point
                                                            }
                                                            className="w-20"
                                                            onChange={(event) =>
                                                                setRow(
                                                                    position,
                                                                    {
                                                                        grade_point:
                                                                            numberFrom(
                                                                                event
                                                                                    .target
                                                                                    .value,
                                                                            ),
                                                                    },
                                                                )
                                                            }
                                                        />
                                                    </td>
                                                    <td className="py-1.5 text-right">
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            aria-label={`Remove grade ${row.grade}`}
                                                            disabled={
                                                                rows.length <= 2
                                                            }
                                                            onClick={() =>
                                                                form.setData(
                                                                    'grades',
                                                                    rows.filter(
                                                                        (
                                                                            _,
                                                                            at,
                                                                        ) =>
                                                                            at !==
                                                                            position,
                                                                    ),
                                                                )
                                                            }
                                                        >
                                                            <Trash2
                                                                aria-hidden
                                                            />
                                                        </Button>
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>

                            <InputError
                                message={errors.grades ?? firstRowError}
                            />

                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={rows.length >= MAX_GRADES}
                                        onClick={() =>
                                            form.setData('grades', [
                                                ...rows,
                                                {
                                                    grade: '',
                                                    min_percent: 0,
                                                    grade_point: 0,
                                                },
                                            ])
                                        }
                                    >
                                        <Plus aria-hidden /> Add grade
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => setConfirmReset(true)}
                                    >
                                        <RotateCcw aria-hidden /> Start from the
                                        default
                                    </Button>
                                </div>
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                >
                                    Save grading scale
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Report card approval</CardTitle>
                        <CardDescription>
                            Who checks a class teacher’s report cards before
                            parents see them.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <label className="flex items-start gap-3 text-sm">
                            <input
                                type="checkbox"
                                className="mt-1 size-4 accent-primary"
                                checked={screen.report_card_approval}
                                onChange={(event) =>
                                    router.put(
                                        approval.url(),
                                        { required: event.target.checked },
                                        { preserveScroll: true },
                                    )
                                }
                            />
                            <span>
                                <strong>Report cards need approval</strong>
                                <br />
                                <span className="text-muted-foreground">
                                    Approved by anyone with “Approve report
                                    cards” for that class. When this is off,
                                    report cards go to parents as soon as the
                                    class teacher sends them.
                                </span>
                            </span>
                        </label>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>How parents see it</CardTitle>
                        <CardDescription>
                            Shown under “How grades work” on the Results page.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-wrap gap-2">
                        {screen.grades.map((band) => (
                            <span
                                key={band.grade}
                                className="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-sm"
                            >
                                <GradePill
                                    grade={band.grade}
                                    low={band.is_lowest}
                                />
                                {band.min_percent}–{band.max_percent}%
                            </span>
                        ))}
                    </CardContent>
                </Card>
            </div>

            {confirmReset && (
                <ConfirmDialog
                    title="Start from the default scale?"
                    body="This fills the table with the CBSE-style starting scale. Nothing changes until you save."
                    confirmLabel="Use the default"
                    onCancel={() => setConfirmReset(false)}
                    onConfirm={() => {
                        form.setData('grades', toRows(screen.default_grades));
                        setConfirmReset(false);
                    }}
                />
            )}
        </>
    );
}

Grading.layout = {
    breadcrumbs: [
        { title: 'Exams and tests', href: index() },
        { title: 'Grading scale', href: grading() },
    ],
};
