import { Card, CardContent } from '@/components/ui/card';

/**
 * A tab whose module is not built yet. The tab stays in place so the page
 * keeps the shape of the prototype.
 */
export default function NotAvailableTab({
    title,
    module,
}: {
    title: string;
    module: string;
}) {
    return (
        <Card>
            <CardContent className="py-10 text-center">
                <p className="font-medium">{title}: not available yet</p>
                <p className="mt-1 text-sm text-muted-foreground">
                    This tab fills in when the {module} module is available.
                </p>
            </CardContent>
        </Card>
    );
}
