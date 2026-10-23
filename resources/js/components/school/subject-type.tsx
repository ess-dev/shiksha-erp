import { Badge } from '@/components/ui/badge';
import type { SubjectKind } from '@/types';

export const SUBJECT_TYPES: { value: SubjectKind; label: string }[] = [
    { value: 'core', label: 'Core (examined)' },
    { value: 'elective', label: 'Elective / language (examined)' },
    {
        value: 'co_scholastic',
        label: 'Co-scholastic (graded on activity, no marks)',
    },
];

const SHORT_LABEL: Record<SubjectKind, string> = {
    core: 'Core',
    elective: 'Elective',
    co_scholastic: 'Co-scholastic',
};

const VARIANT: Record<SubjectKind, 'default' | 'secondary' | 'outline'> = {
    core: 'default',
    elective: 'secondary',
    co_scholastic: 'outline',
};

export function SubjectTypeBadge({ type }: { type: SubjectKind }) {
    return <Badge variant={VARIANT[type]}>{SHORT_LABEL[type]}</Badge>;
}

/**
 * "Class 1, 2, 3" when every name is "Class <something>", otherwise the names
 * joined as they are.
 */
export function classRange(names: string[]): string {
    if (names.length === 0) {
        return '';
    }

    const short = names.map((name) => /^Class (\S+)$/.exec(name)?.[1]);

    return short.every((part) => part !== undefined)
        ? `Class ${short.join(', ')}`
        : names.join(', ');
}
