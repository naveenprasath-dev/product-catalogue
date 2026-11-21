import { Badge } from '@/components/ui/badge';
import type { EnquiryStatus } from '@/types';

// Token classes only (see resources/css/app.css), never raw palette colours.
export function StatusBadge({ status }: { status: EnquiryStatus }) {
    if (status === 'failed') {
        return (
            <Badge variant="destructive" className="capitalize">
                {status}
            </Badge>
        );
    }

    const tone =
        status === 'sent'
            ? 'bg-success text-success-foreground'
            : 'bg-warning text-warning-foreground';

    return (
        <Badge className={`border-transparent capitalize ${tone}`}>
            {status}
        </Badge>
    );
}
