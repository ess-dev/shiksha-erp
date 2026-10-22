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
