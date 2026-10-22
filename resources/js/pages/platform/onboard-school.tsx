import { Head, router } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, Rocket, Trash2 } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import ConfirmDialog from '@/components/platform/confirm-dialog';
import AcademicYearStep from '@/components/platform/onboarding/academic-year-step';
import CampusStep from '@/components/platform/onboarding/campus-step';
import ClassesStep from '@/components/platform/onboarding/classes-step';
import {
    STEPS,
    clearDraft,
    enabledModules,
    freshDraft,
    gradeRange,
    loadDraft,
    parseSections,
    saveDraft,
    sectionsFor,
    toPayload,
} from '@/components/platform/onboarding/draft';
import type { Draft } from '@/components/platform/onboarding/draft';
import ModulesStep from '@/components/platform/onboarding/modules-step';
import OrganizationStep from '@/components/platform/onboarding/organization-step';
import StaffStep from '@/components/platform/onboarding/staff-step';
import StudentsStep from '@/components/platform/onboarding/students-step';
import SubscriptionStep from '@/components/platform/onboarding/subscription-step';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import { store } from '@/routes/platform/schools/onboard';
import type { OnboardingOptions } from '@/types';

type Props = {
    options: OnboardingOptions;
};

const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
const SECTION = /^[A-Z0-9]{1,3}$/;
const LAST_STEP = STEPS.length - 1;

/** Where a server error belongs, by the start of its key. */
function stepOfKey(key: string): number {
    const prefix = key.split(/[.]/)[0];
    const map: Record<string, number> = {
        org: 0,
        subscription: 1,
        campuses: 2,
        academic_year: 3,
        classes: 4,
        class: 4,
        staff: 5,
        modules: 7,
    };

    return map[prefix] ?? 0;
}

/** Checks one step; the server repeats every check. */
function validateStep(
    draft: Draft,
    options: OnboardingOptions,
): Record<string, string> {
    const e: Record<string, string> = {};

    switch (draft.step) {
        case 0:
            if (draft.org.name.trim().length < 3) {
                e['org.name'] = 'Enter the school’s name.';
            }

            if (
                !/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/.test(draft.org.subdomain) ||
                draft.org.subdomain.length < 3
            ) {
                e['org.subdomain'] =
                    'Use 3 or more lowercase letters, digits or hyphens.';
            }

            if (draft.org.city.trim().length < 2) {
                e['org.city'] = 'Enter the city.';
            }

            if (draft.org.contact_name.trim().length < 3) {
                e['org.contact_name'] = 'Enter a contact person.';
            }

            if (!/^[6-9]\d{9}$/.test(draft.org.contact_phone)) {
                e['org.contact_phone'] =
                    'Enter a 10-digit Indian mobile number.';
            }

            if (!EMAIL.test(draft.org.contact_email.trim())) {
                e['org.contact_email'] = 'Enter a valid email.';
            }

            break;
        case 1:
            if (
                !(Math.round(Number(draft.subscription.expected_students)) >= 1)
            ) {
                e['subscription.expected_students'] =
                    'Enter roughly how many students.';
            }

            break;
        case 2:
            draft.campuses.forEach((campus, index) => {
                if (campus.name.trim().length < 2) {
                    e[`campuses.${index}.name`] = 'Name this campus.';
                }
            });

            break;
        case 3:
            if (draft.year.name.trim() === '') {
                e['academic_year.name'] = 'Name the session.';
            }

            if (draft.year.starts_on === '') {
                e['academic_year.starts_on'] = 'Choose the start date.';
            }

            if (
                draft.year.ends_on === '' ||
                draft.year.ends_on <= draft.year.starts_on
            ) {
                e['academic_year.ends_on'] = 'The end must be after the start.';
            }

            break;
        case 4:
            if (draft.classes.mode === 'skip') {
                break;
            }

            if (draft.classes.to < draft.classes.from) {
                e['class.range'] =
                    'Must be the same as or after the first class.';
            }

            if (
                parseSections(draft.classes.sections).length === 0 ||
                parseSections(draft.classes.sections).some(
                    (s) => !SECTION.test(s),
                )
            ) {
                e['class.usual'] = 'Use short names like A, B, C.';
            }

            gradeRange(draft).forEach((grade) => {
                const list = sectionsFor(draft, grade);

                if (list.length === 0) {
                    e[`class.${grade}`] = 'At least one section.';
                } else if (list.some((s) => !SECTION.test(s))) {
                    e[`class.${grade}`] = 'Short names like A, B, C.';
                } else if (list.some((s, i) => list.indexOf(s) !== i)) {
                    e[`class.${grade}`] = 'A section is listed twice.';
                }
            });

            break;
        case 5: {
            draft.staff.forEach((person, index) => {
                if (person.name.trim().length < 2) {
                    e[`staff.${index}.name`] = 'Enter a name.';
                }

                if (!EMAIL.test(person.email.trim())) {
                    e[`staff.${index}.email`] = 'Enter a valid email.';
                }
            });

            const emails = draft.staff.map((p) => p.email.trim().toLowerCase());

            if (
                emails.some(
                    (email, i) => email !== '' && emails.indexOf(email) !== i,
                )
            ) {
                e.staff = 'Each person needs a different email.';
            } else if (!draft.staff.some((p) => p.role === 'principal')) {
                e.staff = 'Invite at least one Principal (the school owner).';
            }

            break;
        }
        case 7: {
            const plan = options.plans.find(
                (p) => p.ulid === draft.subscription.plan,
            );

            if (enabledModules(draft, plan?.module_keys ?? []).length === 0) {
                e.modules = 'Turn on at least one module.';
            }

            break;
        }
    }

    return e;
}

export default function OnboardSchool({ options }: Props) {
    const [draft, setDraft] = useState<Draft>(() => freshDraft(options));
    const [loaded, setLoaded] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [confirming, setConfirming] = useState(false);
    const [discarding, setDiscarding] = useState(false);
    const [processing, setProcessing] = useState(false);
    const title = useRef<HTMLHeadingElement>(null);

    // The saved draft is read after the first render so the server and browser agree.
    useEffect(() => {
        // eslint-disable-next-line react-hooks/set-state-in-effect
        setDraft(loadDraft(options));
        setLoaded(true);
    }, [options]);

    useEffect(() => {
        if (loaded) {
            saveDraft(draft);
        }
    }, [draft, loaded]);

    const update = useCallback(
        (change: (current: Draft) => Draft, clearErrors: string[] = []) => {
            setDraft(change);

            if (clearErrors.length > 0) {
                setErrors((current) => {
                    const next = { ...current };

                    clearErrors.forEach((key) => delete next[key]);

                    return next;
                });
            }
        },
        [],
    );

    const focusFirstError = () =>
        window.requestAnimationFrame(() => {
            document
                .querySelector<HTMLElement>('[aria-invalid="true"]')
                ?.focus();
        });

    const goTo = (step: number) => {
        setDraft((current) => ({ ...current, step }));
        window.requestAnimationFrame(() => title.current?.focus());
    };

    const next = () => {
        const found = validateStep(draft, options);

        if (Object.keys(found).length > 0) {
            setErrors(found);
            focusFirstError();

            return;
        }

        setErrors({});

        if (draft.step === LAST_STEP) {
            setConfirming(true);

            return;
        }

        goTo(draft.step + 1);
    };

    const create = () => {
        const payload = toPayload(draft, options);

        setProcessing(true);
        router.post(store.url(), payload, {
            preserveScroll: true,
            onSuccess: () => clearDraft(),
            onError: (serverErrors) => {
                const mapped: Record<string, string> = {};

                Object.entries(serverErrors).forEach(([key, message]) => {
                    const classMatch = /^classes\.(\d+)\./.exec(key);
                    const level = classMatch
                        ? payload.classes[Number(classMatch[1])]?.level
                        : undefined;

                    mapped[
                        level !== undefined
                            ? `class.${level}`
                            : key.startsWith('modules')
                              ? 'modules'
                              : key
                    ] = message;
                });

                setConfirming(false);
                setErrors(mapped);

                const first = Object.keys(mapped)[0];

                if (first !== undefined) {
                    goTo(stepOfKey(first));
                    focusFirstError();
                }
            },
            onFinish: () => setProcessing(false),
        });
    };

    const discard = () => {
        clearDraft();
        setDraft(freshDraft(options));
        setErrors({});
        setDiscarding(false);
    };

    const plan = options.plans.find((p) => p.ulid === draft.subscription.plan);
    const stepProps = { draft, options, update, errors };
    const step = STEPS[draft.step];
    const accounts = draft.staff.length;

    return (
        <>
            <Head title="Onboard a school" />

            <div className="mb-5 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 className="text-xl font-semibold tracking-tight">
                        Onboard a school
                    </h2>
                    <p className="mt-1 text-sm text-slate-600">
                        {STEPS.length === 8 ? 'Eight' : STEPS.length} short
                        steps. Your progress is saved as a draft in this
                        browser.
                    </p>
                </div>
                <Button variant="ghost" onClick={() => setDiscarding(true)}>
                    <Trash2 aria-hidden="true" />
                    Discard draft
                </Button>
            </div>

            <ol
                className="mb-4 flex gap-2 overflow-x-auto pb-2"
                aria-label="Steps"
            >
                {STEPS.map((item, index) => {
                    const done = index < draft.step;
                    const current = index === draft.step;

                    return (
                        <li
                            key={item.title}
                            aria-current={current ? 'step' : undefined}
                            className={cn(
                                'flex shrink-0 items-center gap-2 rounded-full border bg-card px-3 py-1.5 text-sm font-medium whitespace-nowrap',
                                current && 'border-primary text-primary',
                                done && 'text-green-800',
                                !current && !done && 'text-slate-600',
                            )}
                        >
                            <span
                                aria-hidden="true"
                                className={cn(
                                    'grid size-5 place-items-center rounded-full text-xs font-bold',
                                    current &&
                                        'bg-primary text-primary-foreground',
                                    done && 'bg-green-100 text-green-800',
                                    !current && !done && 'bg-muted',
                                )}
                            >
                                {done ? '✓' : index + 1}
                            </span>
                            {item.title}
                            {done && <span className="sr-only"> (done)</span>}
                        </li>
                    );
                })}
            </ol>

            <form
                noValidate
                onSubmit={(event) => {
                    event.preventDefault();
                    next();
                }}
                className="rounded-xl border border-border bg-card shadow-xs"
            >
                <div className="flex items-start justify-between gap-3 px-5 pt-4">
                    <div>
                        <h3
                            ref={title}
                            tabIndex={-1}
                            className="text-[15px] font-semibold outline-none"
                        >
                            {step.title}
                        </h3>
                        <p className="mt-0.5 text-[13px] text-muted-foreground">
                            {step.subtitle}
                        </p>
                    </div>
                    <span className="shrink-0 text-sm text-muted-foreground">
                        Step {draft.step + 1} of {STEPS.length}
                    </span>
                </div>

                <div className="px-5 py-4">
                    {draft.step === 0 && <OrganizationStep {...stepProps} />}
                    {draft.step === 1 && <SubscriptionStep {...stepProps} />}
                    {draft.step === 2 && <CampusStep {...stepProps} />}
                    {draft.step === 3 && <AcademicYearStep {...stepProps} />}
                    {draft.step === 4 && <ClassesStep {...stepProps} />}
                    {draft.step === 5 && <StaffStep {...stepProps} />}
                    {draft.step === 6 && <StudentsStep />}
                    {draft.step === 7 && <ModulesStep {...stepProps} />}
                </div>

                <div className="flex items-center justify-between border-t border-border px-5 py-3">
                    <Button
                        type="button"
                        variant="outline"
                        disabled={draft.step === 0}
                        onClick={() => goTo(draft.step - 1)}
                    >
                        <ArrowLeft aria-hidden="true" />
                        Back
                    </Button>
                    <Button type="submit" disabled={processing}>
                        {draft.step === LAST_STEP ? (
                            <>
                                <Rocket aria-hidden="true" />
                                Create school
                            </>
                        ) : (
                            <>
                                Next
                                <ArrowRight aria-hidden="true" />
                            </>
                        )}
                    </Button>
                </div>
            </form>

            {confirming && (
                <ConfirmDialog
                    title={`Create ${draft.org.name.trim()}?`}
                    description={`Creates the school on the ${plan?.name ?? ''} plan with ${draft.campuses.length} ${draft.campuses.length === 1 ? 'campus' : 'campuses'}, creates ${accounts} ${accounts === 1 ? 'account' : 'accounts'} with set-password links and ${draft.subscription.start === 'trial' ? `starts a ${options.trial_days}-day trial` : 'starts a paid subscription'}. This takes a few seconds.`}
                    confirmLabel="Create school"
                    processing={processing}
                    onClose={() => setConfirming(false)}
                    onConfirm={create}
                />
            )}

            {discarding && (
                <ConfirmDialog
                    title="Discard this draft?"
                    description="Everything entered so far is cleared."
                    confirmLabel="Discard"
                    destructive
                    onClose={() => setDiscarding(false)}
                    onConfirm={discard}
                />
            )}

            {processing && (
                <p
                    role="status"
                    className="mt-3 flex items-center gap-2 text-sm text-muted-foreground"
                >
                    <Spinner />
                    Creating the school…
                </p>
            )}
        </>
    );
}

OnboardSchool.layout = {
    title: 'Onboard a school',
};
