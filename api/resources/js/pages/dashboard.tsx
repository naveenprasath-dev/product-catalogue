import { Head, Link } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    Clock,
    Package,
    Tags,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import Heading from '@/components/heading';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDateTime } from '@/lib/format';
import { dashboard } from '@/routes/admin';
import { index as enquiriesIndex } from '@/routes/admin/enquiries';
import type { DashboardStats, EnquiryStatus } from '@/types';

type Props = {
    stats: DashboardStats;
};

type Tile = {
    label: string;
    value: number;
    icon: LucideIcon;
    status?: EnquiryStatus;
};

export default function Dashboard({ stats }: Props) {
    const tiles: Tile[] = [
        { label: 'Products', value: stats.products, icon: Package },
        { label: 'Categories', value: stats.categories, icon: Tags },
        {
            label: 'Enquiries sent',
            value: stats.enquiries.sent,
            icon: CheckCircle2,
            status: 'sent',
        },
        {
            label: 'Enquiries pending',
            value: stats.enquiries.pending,
            icon: Clock,
            status: 'pending',
        },
        {
            label: 'Enquiries failed',
            value: stats.enquiries.failed,
            icon: AlertTriangle,
            status: 'failed',
        },
    ];

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <Heading
                    title="Dashboard"
                    description={`Catalogue last imported: ${formatDateTime(stats.last_import_at)}`}
                />
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    {tiles.map((tile) => {
                        const body = (
                            <Card className="h-full gap-2 py-4">
                                <CardHeader className="flex flex-row items-center justify-between px-4">
                                    <CardTitle className="text-sm font-medium text-muted-foreground">
                                        {tile.label}
                                    </CardTitle>
                                    <tile.icon className="size-4 text-muted-foreground" />
                                </CardHeader>
                                <CardContent className="px-4">
                                    <p
                                        className="text-3xl font-semibold"
                                        data-test={`stat-${tile.label}`}
                                    >
                                        {tile.value}
                                    </p>
                                </CardContent>
                            </Card>
                        );

                        return tile.status ? (
                            <Link
                                key={tile.label}
                                href={enquiriesIndex({
                                    query: { status: tile.status },
                                })}
                                className="rounded-xl focus-visible:ring-[3px] focus-visible:ring-ring focus-visible:outline-none"
                            >
                                {body}
                            </Link>
                        ) : (
                            <div key={tile.label}>{body}</div>
                        );
                    })}
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
