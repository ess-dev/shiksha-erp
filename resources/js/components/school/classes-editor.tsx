import { Info, Plus } from 'lucide-react';
import { useState } from 'react';
import AddSectionDialog from '@/components/school/add-section-dialog';
import type { AddSectionTarget } from '@/components/school/add-section-dialog';
import { selectClassName } from '@/components/school/field';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { ClassesOverview, ClassSection } from '@/types';

type ByClass = {
    level: number;
    name: string;
    classUlid: string;
    sections: ClassSection[];
};

function groupByClass(sections: ClassSection[]): ByClass[] {
    const groups = new Map<number, ByClass>();

    for (const section of sections) {
        const group = groups.get(section.class_level) ?? {
            level: section.class_level,
            name: section.class_name,
            classUlid: section.class_ulid,
            sections: [],
        };

        group.sections.push(section);
        groups.set(section.class_level, group);
    }

    return [...groups.values()].sort((a, b) => a.level - b.level);
}

export default function ClassesEditor({
    overview,
    onDone,
}: {
    overview: ClassesOverview;
    onDone: () => void;
}) {
    const [target, setTarget] = useState<AddSectionTarget | null>(null);
    const [picked, setPicked] = useState<Record<string, string>>({});

    const academicYear = overview.academic_year_ulid ?? '';

    return (
        <div className="flex flex-col gap-4">
            <div className="flex items-center gap-3 rounded-lg bg-primary/5 p-3 text-sm text-primary">
                <Info className="size-4 shrink-0" aria-hidden />
                <p className="grow">
                    Classes can have different numbers of sections. A section
                    with students can&rsquo;t be removed; move them first from
                    their profile.
                </p>
                <Button size="sm" onClick={onDone}>
                    Done
                </Button>
            </div>

            {overview.campuses.map((campus) => {
                const sections = overview.sections.filter(
                    (section) => section.campus_ulid === campus.ulid,
                );
                const classes = groupByClass(sections);
                const choice =
                    picked[campus.ulid] ??
                    String(campus.addable_classes[0]?.level ?? '');

                return (
                    <Card key={campus.ulid}>
                        <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-3">
                            <div>
                                <CardTitle>{campus.name}</CardTitle>
                                <CardDescription>
                                    {sections.length}{' '}
                                    {sections.length === 1
                                        ? 'section'
                                        : 'sections'}{' '}
                                    in {classes.length}{' '}
                                    {classes.length === 1 ? 'class' : 'classes'}
                                </CardDescription>
                            </div>
                            {campus.addable_classes.length > 0 && (
                                <div className="flex items-center gap-2">
                                    <label
                                        className="sr-only"
                                        htmlFor={`add-class-${campus.ulid}`}
                                    >
                                        Add a class
                                    </label>
                                    <select
                                        id={`add-class-${campus.ulid}`}
                                        className={`${selectClassName} w-auto`}
                                        value={choice}
                                        onChange={(event) =>
                                            setPicked({
                                                ...picked,
                                                [campus.ulid]:
                                                    event.target.value,
                                            })
                                        }
                                    >
                                        {campus.addable_classes.map(
                                            (option) => (
                                                <option
                                                    key={option.level}
                                                    value={option.level}
                                                >
                                                    {option.name}
                                                </option>
                                            ),
                                        )}
                                    </select>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => {
                                            const option =
                                                campus.addable_classes.find(
                                                    (item) =>
                                                        String(item.level) ===
                                                        choice,
                                                );
                                            const classUlid =
                                                overview.class_ulids[choice];

                                            if (
                                                option === undefined ||
                                                classUlid === undefined
                                            ) {
                                                return;
                                            }

                                            setTarget({
                                                academicYear,
                                                campusUlid: campus.ulid,
                                                campusName: campus.name,
                                                classUlid,
                                                className: option.name,
                                                suggestedName: 'A',
                                            });
                                        }}
                                    >
                                        <Plus aria-hidden />
                                        Add section
                                    </Button>
                                </div>
                            )}
                        </CardHeader>
                        <CardContent>
                            {classes.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No classes at this campus yet.
                                </p>
                            ) : (
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b text-left text-xs text-muted-foreground uppercase">
                                            <th className="py-2 pr-4 font-medium">
                                                Class
                                            </th>
                                            <th className="py-2 pr-4 font-medium">
                                                Sections (students / seats)
                                            </th>
                                            <th className="py-2">
                                                <span className="sr-only">
                                                    Add
                                                </span>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {classes.map((group) => {
                                            const next =
                                                group.sections.find(
                                                    (section) =>
                                                        section.next_section_name !==
                                                        null,
                                                )?.next_section_name ?? null;

                                            return (
                                                <tr
                                                    key={group.level}
                                                    className="border-b last:border-0"
                                                >
                                                    <td className="py-3 pr-4 font-medium whitespace-nowrap">
                                                        {group.name}
                                                        <div className="text-xs font-normal text-muted-foreground">
                                                            {
                                                                group.sections
                                                                    .length
                                                            }{' '}
                                                            {group.sections
                                                                .length === 1
                                                                ? 'section'
                                                                : 'sections'}
                                                        </div>
                                                    </td>
                                                    <td className="py-3 pr-4">
                                                        <div className="flex flex-wrap gap-2">
                                                            {group.sections.map(
                                                                (section) => (
                                                                    <span
                                                                        key={
                                                                            section.ulid
                                                                        }
                                                                        className="rounded-full border px-3 py-1 text-xs"
                                                                    >
                                                                        <strong>
                                                                            {
                                                                                section.name
                                                                            }
                                                                        </strong>{' '}
                                                                        <span className="text-muted-foreground">
                                                                            {
                                                                                section.students
                                                                            }
                                                                            {section.capacity !==
                                                                            null
                                                                                ? `/${section.capacity}`
                                                                                : ''}
                                                                            {section.room
                                                                                ? ` · ${section.room}`
                                                                                : ''}
                                                                        </span>
                                                                    </span>
                                                                ),
                                                            )}
                                                        </div>
                                                    </td>
                                                    <td className="py-3 text-right">
                                                        {next !== null && (
                                                            <Button
                                                                variant="outline"
                                                                size="sm"
                                                                onClick={() =>
                                                                    setTarget({
                                                                        academicYear,
                                                                        campusUlid:
                                                                            campus.ulid,
                                                                        campusName:
                                                                            campus.name,
                                                                        classUlid:
                                                                            group.classUlid,
                                                                        className:
                                                                            group.name,
                                                                        suggestedName:
                                                                            next,
                                                                    })
                                                                }
                                                            >
                                                                <Plus
                                                                    aria-hidden
                                                                />
                                                                Section {next}
                                                            </Button>
                                                        )}
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            )}
                        </CardContent>
                    </Card>
                );
            })}

            {target !== null && (
                <AddSectionDialog
                    key={`${target.campusUlid}-${target.classUlid}-${target.suggestedName}`}
                    target={target}
                    teachers={overview.free_teachers}
                    onClose={() => setTarget(null)}
                />
            )}
        </div>
    );
}
