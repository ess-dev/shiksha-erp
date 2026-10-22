export type PlatformUser = {
    ulid: string;
    name: string;
    email: string;
};

export type PlatformShared = {
    user: PlatformUser | null;
};

export type PlatformOverview = {
    today: string;
    plan_names: string[];
    schools_total: number;
    schools_by_status: Record<
        'trial' | 'active' | 'suspended' | 'expired',
        number
    >;
};

export type TwoStepSetup = {
    qr_code_svg: string;
    secret_key: string;
};

export type PlatformPlan = {
    ulid: string;
    name: string;
    description: string;
    badge: string | null;
    price_per_student_year_paise: number;
    min_students: number;
    module_keys: string[];
    schools_count: number;
};

export type SchoolStatus = 'trial' | 'active' | 'suspended' | 'expired';

export type PlatformSchool = {
    ulid: string;
    name: string;
    city: string | null;
    state: string | null;
    board: string | null;
    campuses: number;
    plan_name: string | null;
    status: SchoolStatus;
    students: number;
    mrr_paise: number;
    trial_ends_on: string | null;
    renews_on: string | null;
};

export type PlatformSchoolList = {
    rows: PlatformSchool[];
    page: number;
    last_page: number;
    matching: number;
    total_schools: number;
    total_students: number;
    status_counts: Record<'all' | SchoolStatus, number>;
    plans: { ulid: string; name: string }[];
};

export type PlatformSchoolFilters = {
    status: SchoolStatus | null;
    plan: string | null;
    q: string;
};

export type PlatformModule = {
    key: string;
    label: string;
};

export type ModuleBadge = 'in_plan' | 'add_on' | 'turned_off';

export type PlatformSchoolDetail = {
    ulid: string;
    name: string;
    city: string | null;
    state: string | null;
    board: string | null;
    campuses: number;
    since_on: string | null;
    status: SchoolStatus;
    suspended_reason: string | null;
    trial_ends_on: string | null;
    renews_on: string | null;
    plan_ulid: string | null;
    plan_min_students: number | null;
    students: number;
    billable_students: number;
    annual_paise: number | null;
    mrr_paise: number;
    contact_name: string | null;
    contact_email: string | null;
    modules: {
        key: string;
        label: string;
        enabled: boolean;
        badge: ModuleBadge | null;
    }[];
    plans: {
        ulid: string;
        name: string;
        price_per_student_year_paise: number;
    }[];
    suspension_reasons: { key: string; label: string }[];
    activity: { summary: string; at: string }[];
    timezone: string;
};
