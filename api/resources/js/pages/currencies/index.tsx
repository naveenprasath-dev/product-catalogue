import { Form, Head, Link } from '@inertiajs/react';
import { Pencil, Plus, RefreshCw, Star, Trash2 } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { formatDateTime } from '@/lib/format';
import {
    create,
    defaultMethod,
    destroy,
    edit,
    index,
    sync,
} from '@/routes/admin/currencies';
import type { AdminCurrency } from '@/types';

type Props = {
    currencies: AdminCurrency[];
    base: string;
    rates_updated_at: string | null;
    rate_source: string;
};

export default function CurrenciesIndex({
    currencies,
    base,
    rates_updated_at,
    rate_source,
}: Props) {
    return (
        <>
            <Head title="Currencies" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Currencies"
                        description={`Prices are stored in ${base}; the shop converts them with these rates.`}
                    />
                    <div className="flex flex-wrap gap-2">
                        <Form
                            {...sync.form()}
                            options={{ preserveScroll: true }}
                        >
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    variant="outline"
                                    disabled={processing}
                                    data-test="sync-rates"
                                >
                                    {processing ? <Spinner /> : <RefreshCw />}
                                    Sync live rates
                                </Button>
                            )}
                        </Form>
                        <Button asChild>
                            <Link href={create()}>
                                <Plus />
                                Add currency
                            </Link>
                        </Button>
                    </div>
                </div>

                <p className="text-sm text-muted-foreground">
                    Rates last updated: {formatDateTime(rates_updated_at)}. Live
                    rates come from {rate_source} (European Central Bank
                    reference rates, published on working days).
                </p>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">Code</th>
                                <th className="px-4 py-3 font-medium">Name</th>
                                <th className="px-4 py-3 font-medium">
                                    Symbol
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Rate (per 1 {base})
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Status
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Rate updated
                                </th>
                                <th className="px-4 py-3">
                                    <span className="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {currencies.map((currency) => (
                                <CurrencyRow
                                    key={currency.code}
                                    currency={currency}
                                />
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

function CurrencyRow({ currency }: { currency: AdminCurrency }) {
    return (
        <tr className="border-t hover:bg-muted/30">
            <td className="px-4 py-3 font-medium">{currency.code}</td>
            <td className="px-4 py-3">{currency.name}</td>
            <td className="px-4 py-3">{currency.symbol}</td>
            <td className="px-4 py-3 text-right tabular-nums">
                {currency.rate}
            </td>
            <td className="px-4 py-3">
                <div className="flex flex-wrap gap-1">
                    {currency.is_default && <Badge>Default</Badge>}
                    {currency.is_base && (
                        <Badge variant="secondary">Base</Badge>
                    )}
                    {currency.enabled ? (
                        <Badge className="border-transparent bg-success text-success-foreground">
                            Enabled
                        </Badge>
                    ) : (
                        <Badge variant="outline">Disabled</Badge>
                    )}
                </div>
            </td>
            <td className="px-4 py-3 whitespace-nowrap text-muted-foreground">
                {formatDateTime(currency.rate_updated_at)}
            </td>
            <td className="px-4 py-3">
                <div className="flex justify-end gap-2">
                    <Button asChild size="sm" variant="outline">
                        <Link href={edit(currency.code)}>
                            <Pencil />
                            Edit
                        </Link>
                    </Button>
                    {currency.enabled && !currency.is_default && (
                        <Form
                            {...defaultMethod.form(currency.code)}
                            options={{ preserveScroll: true }}
                        >
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    size="sm"
                                    variant="outline"
                                    disabled={processing}
                                >
                                    <Star />
                                    Make default
                                </Button>
                            )}
                        </Form>
                    )}
                    {!currency.is_base && !currency.is_default && (
                        <Form
                            {...destroy.form(currency.code)}
                            options={{ preserveScroll: true }}
                            onBefore={() =>
                                window.confirm(
                                    `Delete ${currency.code}? It disappears from the shop.`,
                                )
                            }
                        >
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    size="sm"
                                    variant="outline"
                                    disabled={processing}
                                    aria-label={`Delete ${currency.code}`}
                                >
                                    <Trash2 />
                                </Button>
                            )}
                        </Form>
                    )}
                </div>
            </td>
        </tr>
    );
}

CurrenciesIndex.layout = {
    breadcrumbs: [{ title: 'Currencies', href: index() }],
};
