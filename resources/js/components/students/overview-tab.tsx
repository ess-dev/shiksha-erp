import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { formatCalendarDate } from '@/lib/format';
import { show } from '@/routes/students';
import siblings from '@/routes/students/siblings';
import type { StudentProfile } from '@/types/students';

const NOT_AVAILABLE = 'Not available yet';

function Kpi({ title, note }: { title: string; note: string }) {
    return (
        <Card>
            <CardContent className="space-y-1">
                <p className="text-sm text-muted-foreground">{title}</p>
                <p className="text-2xl font-semibold text-muted-foreground">
                    —
                </p>
                <p className="text-xs text-muted-foreground">{note}</p>
            </CardContent>
        </Card>
    );
}

function Item({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div>
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="text-sm font-medium">{children}</dd>
        </div>
    );
}

export default function OverviewTab({
    student,
    onChangeRoll,
    onLinkSibling,
}: {
    student: StudentProfile;
    onChangeRoll: () => void;
    onLinkSibling: () => void;
}) {
    const [unlinking, setUnlinking] = useState<string | null>(null);

    const unlink = (siblingUlid: string) => {
        router.delete(
            siblings.destroy.url({
                student: student.ulid,
                sibling: siblingUlid,
            }),
            { preserveScroll: true, onFinish: () => setUnlinking(null) },
        );
    };

    return (
        <div className="space-y-4">
            <div className="grid gap-4 md:grid-cols-3">
                <Kpi title="Attendance (30 days)" note={NOT_AVAILABLE} />
                <Kpi title="Overdue fees" note={NOT_AVAILABLE} />
                <Kpi title="Half-yearly result" note={NOT_AVAILABLE} />
            </div>

            <Card>
                <CardContent>
                    <dl className="grid gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-4">
                        <Item label="Admission number">
                            {student.admission_number}
                        </Item>
                        <Item label="Class and roll">
                            {student.class_label ?? '—'}
                            {student.roll_number !== null &&
                                ` · roll ${student.roll_number}`}
                            {student.abilities.manage && student.is_current && (
                                <Button
                                    variant="link"
                                    size="sm"
                                    className="h-auto px-2 py-0"
                                    onClick={onChangeRoll}
                                >
                                    Change roll
                                </Button>
                            )}
                        </Item>
                        <Item label="Campus">{student.campus_name ?? '—'}</Item>
                        <Item label="Class teacher">
                            <span className="text-muted-foreground">
                                {NOT_AVAILABLE}
                            </span>
                        </Item>
                        <Item label="Date of birth">
                            {formatCalendarDate(student.born_on)}
                        </Item>
                        <Item label="Gender">{student.gender}</Item>
                        <Item label="Blood group">{student.blood_group}</Item>
                        <Item label="House">
                            {student.house_name ?? 'Not assigned'}
                        </Item>
                        <Item label="Transport">
                            <span className="text-muted-foreground">
                                Coming soon
                            </span>
                        </Item>
                        <Item label="Admitted on">
                            {formatCalendarDate(student.admitted_on)}
                        </Item>
                        <Item label="Address">{student.address}</Item>
                        <Item label="Status">
                            {student.is_current
                                ? 'Current student'
                                : `${student.status === 'transferred' ? 'Transferred' : 'Left'} on ${student.left_on ? formatCalendarDate(student.left_on) : '—'}${student.tc_number ? ` (TC ${student.tc_number})` : ''}`}
                        </Item>
                    </dl>
                </CardContent>
            </Card>

            <Card>
                <CardContent className="space-y-3">
                    <div className="flex items-center justify-between gap-2">
                        <h2 className="text-sm font-semibold">
                            Siblings in school
                        </h2>
                        {student.abilities.link_siblings &&
                            student.is_current && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={onLinkSibling}
                                >
                                    Link a sibling
                                </Button>
                            )}
                    </div>

                    {student.siblings.length === 0 &&
                    student.hidden_siblings === 0 ? (
                        <p className="text-sm text-muted-foreground">None</p>
                    ) : (
                        <ul className="divide-y text-sm">
                            {student.siblings.map((sibling) => (
                                <li
                                    key={sibling.ulid}
                                    className="flex flex-wrap items-center justify-between gap-2 py-2"
                                >
                                    <span>
                                        <Link
                                            href={show(sibling.ulid)}
                                            className="font-medium text-primary hover:underline"
                                        >
                                            {sibling.name}
                                        </Link>{' '}
                                        ({sibling.class_label}
                                        {sibling.campus_name
                                            ? `, ${sibling.campus_name}`
                                            : ''}
                                        )
                                        {sibling.via === 'staff' && (
                                            <Badge
                                                variant="outline"
                                                className="ml-2"
                                            >
                                                Linked by staff
                                            </Badge>
                                        )}
                                    </span>
                                    {sibling.via === 'staff' &&
                                        student.abilities.link_siblings &&
                                        (unlinking === sibling.ulid ? (
                                            <span className="flex gap-2">
                                                <Button
                                                    variant="destructive"
                                                    size="sm"
                                                    onClick={() =>
                                                        unlink(sibling.ulid)
                                                    }
                                                >
                                                    Confirm unlink
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() =>
                                                        setUnlinking(null)
                                                    }
                                                >
                                                    Keep
                                                </Button>
                                            </span>
                                        ) : (
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() =>
                                                    setUnlinking(sibling.ulid)
                                                }
                                            >
                                                Unlink
                                            </Button>
                                        ))}
                                </li>
                            ))}
                            {student.hidden_siblings > 0 && (
                                <li className="py-2 text-muted-foreground">
                                    {student.hidden_siblings} sibling
                                    {student.hidden_siblings === 1
                                        ? ''
                                        : 's'}{' '}
                                    outside your access
                                </li>
                            )}
                        </ul>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
