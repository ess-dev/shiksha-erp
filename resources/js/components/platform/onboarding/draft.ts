import type { OnboardingOptions } from '@/types';

export type Draft = {
    step: number;
    org: {
        name: string;
        subdomain: string;
        subdomainEdited: boolean;
        board: string;
        city: string;
        state: string;
        contact_name: string;
        contact_phone: string;
        contact_email: string;
    };
    subscription: {
        plan: string;
        start: 'trial' | 'paid';
        expected_students: string;
    };
    campuses: { name: string; address: string }[];
    year: { name: string; starts_on: string; ends_on: string };
    classes: {
        mode: 'now' | 'skip';
        from: number;
        to: number;
        sections: string;
        per_class: Record<string, string>;
    };
    staff: { name: string; email: string; role: string }[];
    /** null until the person changes a switch: then every module of the plan is on. */
    modules: Record<string, boolean> | null;
};

export const STEPS: { title: string; subtitle: string }[] = [
    {
        title: 'Organization',
        subtitle: 'Who the school is and who we’re talking to',
    },
    { title: 'Subscription', subtitle: 'Plan, trial and expected size' },
    { title: 'Campus', subtitle: 'Branches under this school' },
    {
        title: 'Academic year',
        subtitle: 'Session dates used for attendance, fees and exams',
    },
    {
        title: 'Classes and sections',
        subtitle: 'Grades and sections to create',
    },
    {
        title: 'Invite staff',
        subtitle: 'First users; they get a link to set their password',
    },
    { title: 'Import students', subtitle: 'Bring in the student list' },
    { title: 'Activate modules', subtitle: 'What the school sees on day one' },
];

/** Starting values; the dates and class range are illustrative defaults. */
export function freshDraft(options: OnboardingOptions): Draft {
    const growth = options.plans.find((plan) => plan.name === 'Growth');

    return {
        step: 0,
        org: {
            name: '',
            subdomain: '',
            subdomainEdited: false,
            board: options.boards[0] ?? '',
            city: '',
            state: options.states[0] ?? '',
            contact_name: '',
            contact_phone: '',
            contact_email: '',
        },
        subscription: {
            plan: (growth ?? options.plans[0])?.ulid ?? '',
            start: 'trial',
            expected_students: '400',
        },
        campuses: [{ name: 'Main campus', address: '' }],
        year: {
            name: '2026–27',
            starts_on: '2026-04-01',
            ends_on: '2027-03-31',
        },
        classes: {
            mode: 'now',
            from: 1,
            to: 10,
            sections: 'A, B',
            per_class: {},
        },
        staff: [
            { name: '', email: '', role: 'principal' },
            { name: '', email: '', role: 'accountant' },
        ],
        modules: null,
    };
}

const STORAGE_KEY = 'shiksha.platform.onboarding-draft';

/** Drafts live in this browser only; storage can be blocked, so every call is guarded. */
export function loadDraft(options: OnboardingOptions): Draft {
    const fresh = freshDraft(options);

    try {
        const raw = window.localStorage.getItem(STORAGE_KEY);

        if (raw === null) {
            return fresh;
        }

        const saved = JSON.parse(raw) as Partial<Draft>;

        return {
            ...fresh,
            ...saved,
            org: { ...fresh.org, ...saved.org },
            subscription: { ...fresh.subscription, ...saved.subscription },
            classes: { ...fresh.classes, ...saved.classes },
            year: { ...fresh.year, ...saved.year },
        };
    } catch {
        return fresh;
    }
}

export function saveDraft(draft: Draft): void {
    try {
        window.localStorage.setItem(STORAGE_KEY, JSON.stringify(draft));
    } catch {
        // Storage unavailable: the wizard still works, it just cannot keep a draft.
    }
}

export function clearDraft(): void {
    try {
        window.localStorage.removeItem(STORAGE_KEY);
    } catch {
        // Nothing to clear.
    }
}

/** "Holy Heart Convent School" -> "holy-heart-convent-school" (a suggestion only). */
export function suggestSubdomain(name: string): string {
    return name
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 40)
        .replace(/-+$/g, '');
}

/** "A, b c" -> ["A", "B", "C"] */
export function parseSections(value: string): string[] {
    return value
        .split(/[,\s]+/)
        .map((section) => section.trim().toUpperCase())
        .filter(Boolean);
}

export function gradeRange(draft: Draft): number[] {
    const out: number[] = [];

    for (let grade = draft.classes.from; grade <= draft.classes.to; grade++) {
        out.push(grade);
    }

    return out;
}

export function sectionsFor(draft: Draft, grade: number): string[] {
    const own = draft.classes.per_class[String(grade)];

    return own !== undefined
        ? parseSections(own)
        : parseSections(draft.classes.sections);
}

export function gradeName(grade: number): string {
    return grade === 0 ? 'Pre-primary' : `Class ${grade}`;
}

export function enabledModules(
    draft: Draft,
    planModuleKeys: string[],
): string[] {
    return planModuleKeys.filter((key) => draft.modules?.[key] ?? true);
}

/** The request body the server validates. */
export function toPayload(draft: Draft, options: OnboardingOptions) {
    const plan = options.plans.find((p) => p.ulid === draft.subscription.plan);

    return {
        org: {
            name: draft.org.name.trim(),
            subdomain: draft.org.subdomain.trim(),
            board: draft.org.board,
            city: draft.org.city.trim(),
            state: draft.org.state,
            contact_name: draft.org.contact_name.trim(),
            contact_phone: draft.org.contact_phone,
            contact_email: draft.org.contact_email.trim(),
        },
        subscription: {
            plan: draft.subscription.plan,
            start: draft.subscription.start,
        },
        campuses: draft.campuses.map((campus) => ({
            name: campus.name.trim(),
            address: campus.address.trim(),
        })),
        academic_year: {
            name: draft.year.name.trim(),
            starts_on: draft.year.starts_on,
            ends_on: draft.year.ends_on,
        },
        classes:
            draft.classes.mode === 'skip'
                ? []
                : gradeRange(draft).map((level) => ({
                      level,
                      sections: sectionsFor(draft, level),
                  })),
        staff: draft.staff.map((person) => ({
            name: person.name.trim(),
            email: person.email.trim(),
            role: person.role,
        })),
        modules: enabledModules(draft, plan?.module_keys ?? []),
    };
}

export type StepProps = {
    draft: Draft;
    options: OnboardingOptions;
    /** Changes the draft; clears the errors of the keys given. */
    update: (change: (draft: Draft) => Draft, clearErrors?: string[]) => void;
    errors: Record<string, string>;
};
