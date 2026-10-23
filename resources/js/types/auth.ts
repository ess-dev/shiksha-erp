export type User = {
    ulid: string;
    name: string;
    email: string | null;
    phone: string | null;
    avatar?: string;
    two_factor_enabled: boolean;
};

export type Abilities = {
    permissions: string[];
    is_owner: boolean;
};

export type Auth = {
    user: User;
    abilities: Abilities;
    two_step_required: boolean;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
