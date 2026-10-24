import { Link } from '@inertiajs/react';
import { Card } from '@/components/ui/card';
import { grading } from '@/routes/exams';
import type { GradeBand } from '@/types';
import GradePill from './grade-pill';

/** The grading scale as a small table beside the marks sheet. */
export default function GradeScaleCard({
    scale,
    canChange,
}: {
    scale: GradeBand[];
    canChange: boolean;
}) {
    return (
        <Card className="gap-3 p-4">
            <div className="flex items-start justify-between gap-2">
                <div>
                    <h2 className="font-semibold">Grading scale</h2>
                    <p className="text-sm text-muted-foreground">
                        Configurable per school; verify against current board
                        guidance
                    </p>
                </div>
                {canChange && (
                    <Link
                        href={grading()}
                        className="text-sm font-medium text-primary underline-offset-4 hover:underline"
                    >
                        Change
                    </Link>
                )}
            </div>
            <table className="w-full text-sm">
                <caption className="sr-only">Grades and percentages</caption>
                <tbody>
                    {scale.map((band) => (
                        <tr key={band.grade}>
                            <td className="py-1.5">
                                <GradePill
                                    grade={band.grade}
                                    low={band.is_lowest}
                                />
                            </td>
                            <td className="py-1.5 tabular-nums">
                                {band.min_percent}–{band.max_percent}%
                            </td>
                            <td className="py-1.5 text-right text-muted-foreground">
                                Grade point {band.grade_point}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </Card>
    );
}
