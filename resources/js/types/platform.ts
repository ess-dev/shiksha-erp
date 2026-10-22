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

export type PlatformModule = {
    key: string;
    label: string;
};
