declare namespace App {
    namespace Domain {
        namespace Access {
            namespace Enums {
                export type PermissionRisk =
                    | 'normal'
                    | 'sensitive'
                    | 'critical';
                export type ScopeType =
                    | 'school'
                    | 'campus'
                    | 'wing'
                    | 'sections';
            }
        }
        namespace Audit {
            namespace Enums {
                export type AuditActorType =
                    | 'user'
                    | 'staff'
                    | 'guardian'
                    | 'platform'
                    | 'system';
                export type AuditScope = 'tenant' | 'platform';
            }
        }
        namespace Platform {
            namespace Enums {
                export type SuspensionReason =
                    | 'invoice_overdue'
                    | 'requested_by_school'
                    | 'terms_review';
            }
        }
    }
}
declare namespace Illuminate {
    export type CursorPaginator<TKey, TValue> = {
        data: TKey extends string ? Record<TKey, TValue> : TValue[];
        links: {
            url: string | null;
            label: string;
            active: boolean;
        }[];
        meta: {
            path: string;
            per_page: number;
            next_cursor: string | null;
            next_page_url: string | null;
            prev_cursor: string | null;
            prev_page_url: string | null;
        };
    };
    export type CursorPaginatorInterface<TKey, TValue> =
        Illuminate.CursorPaginator<TKey, TValue>;
    export type LengthAwarePaginator<TKey, TValue> = {
        data: TKey extends string ? Record<TKey, TValue> : TValue[];
        links: {
            url: string | null;
            label: string;
            active: boolean;
        }[];
        meta: {
            total: number;
            current_page: number;
            first_page_url: string;
            from: number | null;
            last_page: number;
            last_page_url: string;
            next_page_url: string | null;
            path: string;
            per_page: number;
            prev_page_url: string | null;
            to: number | null;
        };
    };
    export type LengthAwarePaginatorInterface<TKey, TValue> =
        Illuminate.LengthAwarePaginator<TKey, TValue>;
}
declare namespace Spatie {
    namespace LaravelData {
        export type CursorPaginatedDataCollection<TKey, TValue> =
            Illuminate.CursorPaginator<TKey, TValue>;
        export type PaginatedDataCollection<TKey, TValue> =
            Illuminate.LengthAwarePaginator<TKey, TValue>;
    }
}
