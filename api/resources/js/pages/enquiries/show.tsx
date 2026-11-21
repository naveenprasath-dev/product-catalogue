import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { ResendButton } from '@/components/admin/resend-button';
import { StatusBadge } from '@/components/admin/status-badge';
import Heading from '@/components/heading';
import { Card, CardContent } from '@/components/ui/card';
import { formatDateTime } from '@/lib/format';
import { index } from '@/routes/admin/enquiries';
import type { Enquiry } from '@/types';

type Props = {
    enquiry: Enquiry;
};

function Field({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="grid gap-1 sm:grid-cols-[10rem_1fr]">
            <dt className="text-sm text-muted-foreground">{label}</dt>
            <dd className="text-sm break-words">{children}</dd>
        </div>
    );
}

export default function EnquiryShow({ enquiry }: Props) {
    return (
        <>
            <Head title={`Enquiry #${enquiry.id}`} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={`Enquiry #${enquiry.id}`}
                        description={`Received ${formatDateTime(enquiry.created_at)}`}
                    />
                    {enquiry.resendable && (
                        <ResendButton enquiryId={enquiry.id} />
                    )}
                </div>

                <Card>
                    <CardContent>
                        <dl className="grid gap-4">
                            <Field label="Status">
                                <StatusBadge status={enquiry.status} />
                            </Field>
                            <Field label="Emailed to sales">
                                {formatDateTime(enquiry.mailed_at)}
                            </Field>
                            <Field label="Product">
                                {enquiry.product_title} (#{enquiry.product_id})
                            </Field>
                            <Field label="Name">{enquiry.name}</Field>
                            <Field label="Email">
                                <a
                                    href={`mailto:${enquiry.email}`}
                                    className="underline underline-offset-4"
                                >
                                    {enquiry.email}
                                </a>
                            </Field>
                            <Field label="Phone">{enquiry.phone ?? '—'}</Field>
                            <Field label="Account">
                                {enquiry.customer_account
                                    ? 'Customer account'
                                    : 'Guest'}
                            </Field>
                            <Field label="Message">
                                <span className="whitespace-pre-line">
                                    {enquiry.message ?? '—'}
                                </span>
                            </Field>
                        </dl>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

EnquiryShow.layout = {
    breadcrumbs: [{ title: 'Enquiries', href: index() }],
};
