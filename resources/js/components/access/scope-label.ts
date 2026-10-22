import type { RoleAssignmentRow, ScopeOptions } from '@/types/access';

/** A short phrase for the part of the school an assignment covers. */
export default function scopeLabel(
    assignment: RoleAssignmentRow,
    options: ScopeOptions | null,
): string {
    const campusNames = assignment.campus_ulids.map(
        (ulid) =>
            options?.campuses.find((campus) => campus.ulid === ulid)?.name ??
            'a campus',
    );

    switch (assignment.scope_type) {
        case 'school':
            return 'Whole school';
        case 'campus':
            return campusNames.join(', ');
        case 'wing': {
            const level = (value: number | null) =>
                options?.levels.find((l) => l.level === value)?.name ??
                `level ${value}`;

            return `${level(assignment.from_level)} to ${level(assignment.to_level)} at ${campusNames.join(', ')}`;
        }
        case 'sections': {
            const names = assignment.section_ulids.map(
                (ulid) =>
                    options?.sections.find((section) => section.ulid === ulid)
                        ?.label,
            );
            const known = names.filter((name): name is string => !!name);

            return known.length === names.length && known.length <= 3
                ? known.join(', ')
                : `${names.length} sections`;
        }
    }
}
