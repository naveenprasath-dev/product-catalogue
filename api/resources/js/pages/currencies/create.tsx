import { Head } from '@inertiajs/react';
import { CurrencyForm } from '@/components/admin/currency-form';
import Heading from '@/components/heading';
import { create, index } from '@/routes/admin/currencies';

export default function CurrencyCreate({ base }: { base: string }) {
    return (
        <>
            <Head title="Add currency" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <Heading
                    title="Add currency"
                    description="New currencies are not the default; make one the default from the list."
                />
                <CurrencyForm base={base} />
            </div>
        </>
    );
}

CurrencyCreate.layout = {
    breadcrumbs: [
        { title: 'Currencies', href: index() },
        { title: 'Add', href: create() },
    ],
};
