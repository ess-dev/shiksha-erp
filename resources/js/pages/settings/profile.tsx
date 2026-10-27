import { Form, Head, router, usePage } from '@inertiajs/react';
import { Camera } from 'lucide-react';
import { useRef, useState } from 'react';
import type { ReactNode } from 'react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import InputError from '@/components/input-error';
import InitialsBadge from '@/components/students/initials-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatCalendarDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import { channels, edit, photo } from '@/routes/profile';
import {
    destroy as destroyPhoto,
    store as storePhoto,
} from '@/routes/profile/photo';
import type { Auth, StaffProfile } from '@/types';

type PageProps = {
    auth: Auth;
};

const RELATIONS = [
    'Spouse',
    'Father',
    'Mother',
    'Brother',
    'Sister',
    'Son',
    'Daughter',
    'Friend',
    'Other',
];

const CHANNELS = [
    { key: 'push', label: 'Push' },
    { key: 'sms', label: 'SMS' },
    { key: 'whatsapp', label: 'WhatsApp' },
    { key: 'email', label: 'Email' },
] as const;

const fieldClass =
    'border-input bg-background w-full rounded-md border px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';

/** A +91 number as the ten digits the person typed; anything else as stored. */
function nationalNumber(value: string | null | undefined): string {
    return (value ?? '').replace(/^\+91(?=\d{10}$)/, '');
}

function Field({
    id,
    label,
    hint,
    error,
    children,
}: {
    id: string;
    label: string;
    hint?: string;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            {children}
            {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
            <InputError message={error} />
        </div>
    );
}

function Detail({ label, value }: { label: string; value: string | null }) {
    return (
        <div>
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="text-sm font-medium">{value || '—'}</dd>
        </div>
    );
}

export default function Profile({ profile }: { profile: StaffProfile | null }) {
    const { auth } = usePage<PageProps>().props;
    const fileInput = useRef<HTMLInputElement>(null);
    const [photoVersion, setPhotoVersion] = useState(0);
    const [photoError, setPhotoError] = useState<string | null>(null);
    const [channelState, setChannelState] = useState(profile?.channels);

    const uploadPhoto = (file: File | undefined) => {
        if (!file) {
            return;
        }

        setPhotoError(null);
        router.post(
            storePhoto(),
            { photo: file },
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => setPhotoVersion((v) => v + 1),
                onError: (errors) => setPhotoError(errors.photo ?? null),
            },
        );
    };

    const toggleChannel = (
        key: (typeof CHANNELS)[number]['key'],
        on: boolean,
    ) => {
        setChannelState((current) =>
            current ? { ...current, [key]: on } : current,
        );
        router.patch(
            channels(),
            { [key]: on },
            { preserveScroll: true, preserveState: true },
        );
    };

    return (
        <>
            <Head title="Profile settings" />

            <h1 className="sr-only">Profile settings</h1>

            <div className="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Personal details</CardTitle>
                        <CardDescription>
                            Your details and how the school reaches you.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        {profile && (
                            <div className="flex items-center gap-4">
                                {profile.has_photo ? (
                                    <img
                                        src={`${photo.url()}?v=${photoVersion}`}
                                        alt=""
                                        className="size-[72px] rounded-full object-cover"
                                    />
                                ) : (
                                    <InitialsBadge
                                        name={profile.full_name}
                                        className="size-[72px] text-xl"
                                    />
                                )}
                                <div className="space-y-2">
                                    <div>
                                        <p className="font-semibold">
                                            {profile.full_name}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {[
                                                profile.job_title,
                                                profile.school_name,
                                            ]
                                                .filter(Boolean)
                                                .join(' · ')}
                                        </p>
                                    </div>
                                    <div className="flex gap-2">
                                        <input
                                            ref={fileInput}
                                            type="file"
                                            accept="image/jpeg,image/png,image/webp"
                                            className="sr-only"
                                            aria-label="Choose a photo"
                                            onChange={(e) =>
                                                uploadPhoto(e.target.files?.[0])
                                            }
                                        />
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            onClick={() =>
                                                fileInput.current?.click()
                                            }
                                        >
                                            <Camera /> Change photo
                                        </Button>
                                        {profile.has_photo && (
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="ghost"
                                                onClick={() =>
                                                    router.delete(
                                                        destroyPhoto(),
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            >
                                                Remove
                                            </Button>
                                        )}
                                    </div>
                                    <InputError
                                        message={photoError ?? undefined}
                                    />
                                </div>
                            </div>
                        )}

                        <Form
                            {...ProfileController.update.form()}
                            options={{ preserveScroll: true }}
                            className="space-y-6"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-6 sm:grid-cols-2">
                                        {profile && (
                                            <Field
                                                id="full_name"
                                                label="Name"
                                                hint="Your legal name is changed by the school office."
                                            >
                                                <Input
                                                    id="full_name"
                                                    value={profile.full_name}
                                                    disabled
                                                />
                                            </Field>
                                        )}
                                        <Field
                                            id="work_email"
                                            label="Work email"
                                        >
                                            <Input
                                                id="work_email"
                                                value={auth.user.email ?? ''}
                                                disabled
                                            />
                                        </Field>
                                        <Field
                                            id="phone"
                                            label="Mobile"
                                            hint="You sign in with this number. Parents never see it."
                                            error={errors.phone}
                                        >
                                            <Input
                                                id="phone"
                                                name="phone"
                                                type="tel"
                                                autoComplete="tel"
                                                defaultValue={nationalNumber(
                                                    auth.user.phone,
                                                )}
                                            />
                                        </Field>
                                        {profile && (
                                            <>
                                                <Field
                                                    id="personal_email"
                                                    label="Personal email"
                                                    error={
                                                        errors.personal_email
                                                    }
                                                >
                                                    <Input
                                                        id="personal_email"
                                                        name="personal_email"
                                                        type="email"
                                                        defaultValue={
                                                            profile.personal_email ??
                                                            ''
                                                        }
                                                    />
                                                </Field>
                                                <Field
                                                    id="qualifications"
                                                    label="Qualifications"
                                                    error={
                                                        errors.qualifications
                                                    }
                                                >
                                                    <Input
                                                        id="qualifications"
                                                        name="qualifications"
                                                        maxLength={100}
                                                        defaultValue={
                                                            profile.qualifications ??
                                                            ''
                                                        }
                                                    />
                                                </Field>
                                                <Field
                                                    id="languages"
                                                    label="Languages"
                                                    error={errors.languages}
                                                >
                                                    <Input
                                                        id="languages"
                                                        name="languages"
                                                        maxLength={80}
                                                        defaultValue={
                                                            profile.languages ??
                                                            ''
                                                        }
                                                    />
                                                </Field>
                                            </>
                                        )}
                                    </div>

                                    {profile && (
                                        <>
                                            <Field
                                                id="home_address"
                                                label="Home address"
                                                error={errors.home_address}
                                            >
                                                <textarea
                                                    id="home_address"
                                                    name="home_address"
                                                    maxLength={200}
                                                    rows={2}
                                                    className={cn(
                                                        fieldClass,
                                                        'py-2',
                                                    )}
                                                    defaultValue={
                                                        profile.home_address ??
                                                        ''
                                                    }
                                                />
                                            </Field>
                                            <Field
                                                id="about"
                                                label="About me"
                                                hint="A line colleagues see on the school feed."
                                                error={errors.about}
                                            >
                                                <textarea
                                                    id="about"
                                                    name="about"
                                                    maxLength={240}
                                                    rows={2}
                                                    className={cn(
                                                        fieldClass,
                                                        'py-2',
                                                    )}
                                                    defaultValue={
                                                        profile.about ?? ''
                                                    }
                                                />
                                            </Field>

                                            <fieldset className="space-y-4">
                                                <legend className="mb-2 text-sm font-medium">
                                                    Emergency contact
                                                </legend>
                                                <div className="grid gap-4 sm:grid-cols-2">
                                                    <Field
                                                        id="emergency_name"
                                                        label="Name"
                                                        error={
                                                            errors.emergency_name
                                                        }
                                                    >
                                                        <Input
                                                            id="emergency_name"
                                                            name="emergency_name"
                                                            maxLength={60}
                                                            defaultValue={
                                                                profile.emergency_name ??
                                                                ''
                                                            }
                                                        />
                                                    </Field>
                                                    <Field
                                                        id="emergency_relation"
                                                        label="Relation"
                                                        error={
                                                            errors.emergency_relation
                                                        }
                                                    >
                                                        <select
                                                            id="emergency_relation"
                                                            name="emergency_relation"
                                                            className={cn(
                                                                fieldClass,
                                                                'h-9',
                                                            )}
                                                            defaultValue={
                                                                profile.emergency_relation ??
                                                                'Spouse'
                                                            }
                                                        >
                                                            {RELATIONS.map(
                                                                (r) => (
                                                                    <option
                                                                        key={r}
                                                                    >
                                                                        {r}
                                                                    </option>
                                                                ),
                                                            )}
                                                        </select>
                                                    </Field>
                                                    <Field
                                                        id="emergency_phone"
                                                        label="Mobile"
                                                        error={
                                                            errors.emergency_phone
                                                        }
                                                    >
                                                        <Input
                                                            id="emergency_phone"
                                                            name="emergency_phone"
                                                            type="tel"
                                                            defaultValue={nationalNumber(
                                                                profile.emergency_phone,
                                                            )}
                                                        />
                                                    </Field>
                                                </div>
                                            </fieldset>
                                        </>
                                    )}

                                    <div className="flex items-center gap-4">
                                        <Button
                                            disabled={processing}
                                            data-test="update-profile-button"
                                        >
                                            Save
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>

                {profile && (
                    <>
                        <Card>
                            <CardHeader>
                                <CardTitle>Work details</CardTitle>
                                <CardDescription>
                                    Kept by the school office. Ask the principal
                                    if something is wrong.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-6">
                                <dl className="grid gap-4 sm:grid-cols-2">
                                    <Detail
                                        label="Staff ID"
                                        value={profile.employee_code}
                                    />
                                    <Detail
                                        label="Job title"
                                        value={profile.job_title}
                                    />
                                    <Detail
                                        label="Department"
                                        value={profile.department}
                                    />
                                    <Detail
                                        label="Campuses"
                                        value={profile.campuses.join(', ')}
                                    />
                                    <Detail
                                        label="Joined"
                                        value={
                                            profile.joined_on
                                                ? formatCalendarDate(
                                                      profile.joined_on,
                                                  )
                                                : null
                                        }
                                    />
                                    {profile.class_teacher_of.length > 0 && (
                                        <Detail
                                            label="Class teacher of"
                                            value={profile.class_teacher_of.join(
                                                ', ',
                                            )}
                                        />
                                    )}
                                    {profile.subjects.length > 0 && (
                                        <Detail
                                            label="Subjects"
                                            value={profile.subjects.join(', ')}
                                        />
                                    )}
                                </dl>

                                <div>
                                    <h3 className="mb-2 text-sm font-semibold">
                                        Roles and access
                                    </h3>
                                    {profile.roles.length === 0 ? (
                                        <p className="text-sm text-muted-foreground">
                                            No roles
                                        </p>
                                    ) : (
                                        <ul className="flex flex-wrap gap-2">
                                            {profile.roles.map(
                                                (role, index) => (
                                                    <li
                                                        key={`${role.role_name}-${index}`}
                                                    >
                                                        <Badge
                                                            variant="outline"
                                                            className="gap-1.5"
                                                        >
                                                            <strong>
                                                                {role.role_name}
                                                            </strong>
                                                            <span className="font-normal text-muted-foreground">
                                                                {
                                                                    role.scope_label
                                                                }
                                                            </span>
                                                        </Badge>
                                                    </li>
                                                ),
                                            )}
                                        </ul>
                                    )}
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>How we reach you</CardTitle>
                                <CardDescription>
                                    Approvals and reminders come to the bell and
                                    these channels.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {CHANNELS.map(({ key, label }) => {
                                    const unavailable =
                                        key === 'whatsapp' &&
                                        !profile.whatsapp_in_plan;

                                    return (
                                        <label
                                            key={key}
                                            className={cn(
                                                'flex items-center gap-3 text-sm',
                                                unavailable && 'opacity-60',
                                            )}
                                        >
                                            <input
                                                type="checkbox"
                                                className="size-4 accent-primary"
                                                checked={
                                                    channelState?.[key] ?? false
                                                }
                                                disabled={unavailable}
                                                onChange={(e) =>
                                                    toggleChannel(
                                                        key,
                                                        e.target.checked,
                                                    )
                                                }
                                            />
                                            <span>{label}</span>
                                            {unavailable && (
                                                <Badge variant="secondary">
                                                    Not in the school's plan
                                                </Badge>
                                            )}
                                        </label>
                                    );
                                })}
                            </CardContent>
                        </Card>
                    </>
                )}
            </div>
        </>
    );
}

Profile.layout = {
    breadcrumbs: [
        {
            title: 'Profile settings',
            href: edit(),
        },
    ],
};
