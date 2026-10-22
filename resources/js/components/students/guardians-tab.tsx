import InitialsBadge from '@/components/students/initials-badge';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import type { StudentProfile } from '@/types/students';

const APP_STATUS: Record<
    string,
    { label: string; variant: 'default' | 'secondary' | 'outline' }
> = {
    active: { label: 'Active', variant: 'default' },
    invited: { label: 'Invited', variant: 'outline' },
    not_invited: { label: 'Not invited', variant: 'secondary' },
};

export default function GuardiansTab({ student }: { student: StudentProfile }) {
    return (
        <div className="space-y-3">
            <div className="grid gap-4 md:grid-cols-2">
                {student.guardians.map((guardian) => {
                    const status =
                        APP_STATUS[guardian.parent_app] ??
                        APP_STATUS.not_invited;

                    return (
                        <Card key={guardian.ulid}>
                            <CardContent className="space-y-4">
                                <div className="flex items-center gap-3">
                                    <InitialsBadge name={guardian.name} />
                                    <div>
                                        <div className="font-medium">
                                            {guardian.name}
                                        </div>
                                        <div className="text-sm text-muted-foreground">
                                            {guardian.relation}
                                            {guardian.is_primary &&
                                                ' · primary contact'}
                                        </div>
                                    </div>
                                </div>

                                <dl className="grid grid-cols-2 gap-3 text-sm">
                                    <div>
                                        <dt className="text-xs text-muted-foreground">
                                            Phone
                                        </dt>
                                        <dd className="font-medium">
                                            {guardian.phone}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-xs text-muted-foreground">
                                            Email
                                        </dt>
                                        <dd className="font-medium break-all">
                                            {guardian.email ?? '—'}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-xs text-muted-foreground">
                                            Occupation
                                        </dt>
                                        <dd className="font-medium">
                                            {guardian.occupation ?? '—'}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-xs text-muted-foreground">
                                            Parent app
                                        </dt>
                                        <dd>
                                            <Badge variant={status.variant}>
                                                {status.label}
                                            </Badge>
                                        </dd>
                                    </div>
                                </dl>

                                {(guardian.other_children.length > 0 ||
                                    guardian.hidden_other_children > 0) && (
                                    <p className="text-sm text-muted-foreground">
                                        Also the guardian of{' '}
                                        {[
                                            ...guardian.other_children,
                                            ...(guardian.hidden_other_children >
                                            0
                                                ? [
                                                      `${guardian.hidden_other_children} more outside your access`,
                                                  ]
                                                : []),
                                        ].join(', ')}
                                        .
                                    </p>
                                )}
                            </CardContent>
                        </Card>
                    );
                })}
            </div>

            {!student.abilities.view_sensitive && (
                <p className="text-xs text-muted-foreground">
                    Phone numbers are partly hidden. Only staff with permission
                    see full contact details.
                </p>
            )}
        </div>
    );
}
