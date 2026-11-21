import { Form } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store, update } from '@/routes/admin/currencies';
import type { AdminCurrency } from '@/types';

/**
 * Create (no `currency`) or edit a currency. The server enforces every rule; the base currency's
 * rate and enabled flag are only shown read-only here for clarity.
 */
export function CurrencyForm({
    currency,
    base,
}: {
    currency?: AdminCurrency;
    base: string;
}) {
    const isBase = currency?.is_base ?? false;
    const action = currency ? update.form(currency.code) : store.form();

    return (
        <Form {...action} className="grid max-w-xl gap-6">
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor="code">Code</Label>
                        {currency ? (
                            <Input
                                id="code"
                                value={currency.code}
                                readOnly
                                disabled
                            />
                        ) : (
                            <Input
                                id="code"
                                name="code"
                                required
                                maxLength={3}
                                autoComplete="off"
                                placeholder="EUR"
                                className="uppercase"
                            />
                        )}
                        <p className="text-xs text-muted-foreground">
                            3-letter ISO 4217 code. It can't be changed later.
                        </p>
                        <InputError message={errors.code} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="name">Name</Label>
                        <Input
                            id="name"
                            name="name"
                            required
                            maxLength={100}
                            defaultValue={currency?.name}
                            placeholder="Euro"
                        />
                        <InputError message={errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="symbol">Symbol</Label>
                        <Input
                            id="symbol"
                            name="symbol"
                            required
                            maxLength={10}
                            defaultValue={currency?.symbol}
                            placeholder="€"
                        />
                        <InputError message={errors.symbol} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="rate">Rate (per 1 {base})</Label>
                        <Input
                            id="rate"
                            name="rate"
                            type="number"
                            inputMode="decimal"
                            step="any"
                            min="0"
                            required
                            readOnly={isBase}
                            defaultValue={currency?.rate ?? ''}
                            placeholder="0.92"
                        />
                        <p className="text-xs text-muted-foreground">
                            {isBase
                                ? `${base} is the base currency: its rate is always 1.`
                                : `Prices are stored in ${base} and multiplied by this rate. "Sync live rates" overwrites it.`}
                        </p>
                        <InputError message={errors.rate} />
                    </div>

                    <div className="grid gap-2">
                        <div className="flex items-center gap-3">
                            {isBase && (
                                // A disabled checkbox is not submitted; the base currency is always enabled.
                                <input type="hidden" name="enabled" value="1" />
                            )}
                            <Checkbox
                                id="enabled"
                                name={isBase ? undefined : 'enabled'}
                                defaultChecked={currency?.enabled ?? true}
                                disabled={isBase}
                            />
                            <Label htmlFor="enabled">
                                Enabled (shown in the shop)
                            </Label>
                        </div>
                        <InputError message={errors.enabled} />
                    </div>

                    <div>
                        <Button
                            type="submit"
                            disabled={processing}
                            data-test="save-currency"
                        >
                            {processing && <Spinner />}
                            {currency ? 'Save' : 'Add currency'}
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
