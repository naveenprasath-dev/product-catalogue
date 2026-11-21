import { Head } from '@inertiajs/react';
import { CurrencyForm } from '@/components/admin/currency-form';
import Heading from '@/components/heading';
import { index } from '@/routes/admin/currencies';
import type { AdminCurrency } from '@/types';

type Props = {
    currency: AdminCurrency;
    base: string;
};

export default function CurrencyEdit({ currency, base }: Props) {
    return (
        <>
            <Head title={`Edit ${currency.code}`} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <Heading
                    title={`Edit ${currency.code}`}
                    description={currency.name}
                />
                <CurrencyForm currency={currency} base={base} />
            </div>
        </>
    );
}

CurrencyEdit.layout = {
    breadcrumbs: [{ title: 'Currencies', href: index() }],
};
