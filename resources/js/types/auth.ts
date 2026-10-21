export type User = {
    ulid: string;
    name: string;
    email: string | null;
    phone: string | null;
    avatar?: string;
    two_factor_enabled: boolean;
};

export type Auth = {
    user: User;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
