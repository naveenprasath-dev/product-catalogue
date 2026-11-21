import { Head, Link } from '@inertiajs/react';
import { ResendButton } from '@/components/admin/resend-button';
import { StatusBadge } from '@/components/admin/status-badge';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/lib/format';
import { index, show } from '@/routes/admin/enquiries';
import type { Enquiry, EnquiryStatus, Paginated } from '@/types';

type Props = {
    enquiries: Paginated<Enquiry>;
    filters: { status: EnquiryStatus | null };
};

const filterOptions: { label: string; status: EnquiryStatus | null }[] = [
    { label: 'All', status: null },
    { label: 'Pending', status: 'pending' },
    { label: 'Sent', status: 'sent' },
    { label: 'Failed', status: 'failed' },
];

function pageUrl(status: EnquiryStatus | null, page: number) {
    const query: Record<string, string | number> = {};
    if (status) {
        query.status = status;
    }
    if (page > 1) {
        query.page = page;
    }

    return index({ query });
}

export default function EnquiriesIndex({ enquiries, filters }: Props) {
    const { data, meta } = enquiries;

    return (
        <>
            <Head title="Enquiries" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <Heading
                    title="Enquiries"
                    description="Customer enquiries, newest first."
                />

                <nav
                    aria-label="Filter by status"
                    className="flex flex-wrap gap-2"
                >
                    {filterOptions.map((option) => {
                        const active = option.status === filters.status;

                        return (
                            <Button
                                key={option.label}
                                asChild
                                size="sm"
                                variant={active ? 'default' : 'outline'}
                            >
                                <Link
                                    href={pageUrl(option.status, 1)}
                                    aria-current={active ? 'page' : undefined}
                                    preserveScroll
                                >
                                    {option.label}
                                </Link>
                            </Button>
                        );
                    })}
                </nav>

                {data.length === 0 ? (
                    <p className="rounded-xl border p-8 text-center text-muted-foreground">
                        No enquiries
                        {filters.status ? ` with status ${filters.status}` : ''}
                        .
                    </p>
                ) : (
                    <div className="overflow-x-auto rounded-xl border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left text-muted-foreground">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        Received
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Customer
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Product
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Status
                                    </th>
                                    <th className="px-4 py-3">
                                        <span className="sr-only">Actions</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {data.map((enquiry) => (
                                    <tr
                                        key={enquiry.id}
                                        className="border-t hover:bg-muted/30"
                                    >
                                        <td className="px-4 py-3 whitespace-nowrap">
                                            <Link
                                                href={show(enquiry.id)}
                                                className="font-medium underline-offset-4 hover:underline"
                                            >
                                                {formatDateTime(
                                                    enquiry.created_at,
                                                )}
                                            </Link>
                                        </td>
                                        <td className="px-4 py-3">
                                            <div>{enquiry.name}</div>
                                            <div className="text-xs text-muted-foreground">
                                                {enquiry.email}
                                                {enquiry.customer_account &&
                                                    ' · customer account'}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            {enquiry.product_title}
                                        </td>
                                        <td className="px-4 py-3">
                                            <StatusBadge
                                                status={enquiry.status}
                                            />
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            {enquiry.resendable && (
                                                <ResendButton
                                                    enquiryId={enquiry.id}
                                                    size="sm"
                                                />
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {meta.last_page > 1 && (
                    <nav
                        aria-label="Pagination"
                        className="flex items-center justify-between gap-4 text-sm"
                    >
                        <span className="text-muted-foreground">
                            {meta.from}–{meta.to} of {meta.total}
                        </span>
                        <div className="flex gap-2">
                            <PageLink
                                label="Previous"
                                disabled={meta.current_page <= 1}
                                href={pageUrl(
                                    filters.status,
                                    meta.current_page - 1,
                                )}
                            />
                            <PageLink
                                label="Next"
                                disabled={meta.current_page >= meta.last_page}
                                href={pageUrl(
                                    filters.status,
                                    meta.current_page + 1,
                                )}
                            />
                        </div>
                    </nav>
                )}
            </div>
        </>
    );
}

function PageLink({
    label,
    href,
    disabled,
}: {
    label: string;
    href: ReturnType<typeof index>;
    disabled: boolean;
}) {
    if (disabled) {
        return (
            <Button size="sm" variant="outline" disabled>
                {label}
            </Button>
        );
    }

    return (
        <Button asChild size="sm" variant="outline">
            <Link href={href}>{label}</Link>
        </Button>
    );
}

EnquiriesIndex.layout = {
    breadcrumbs: [{ title: 'Enquiries', href: index() }],
};
