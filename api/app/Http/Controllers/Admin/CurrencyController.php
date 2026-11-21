<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Catalogue\Currency\CurrencyService;
use App\Catalogue\Data\Currency;
use App\Catalogue\Exceptions\CurrencyChangeRejected;
use App\Catalogue\Exceptions\CurrencyNotFound;
use App\Catalogue\Exceptions\RatesUnavailable;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCurrencyRequest;
use App\Http\Requests\Admin\UpdateCurrencyRequest;
use App\Http\Resources\Admin\CurrencyResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Currencies and exchange rates. Rules live in CurrencyService; rejected changes become field errors or toasts. */
final class CurrencyController extends Controller
{
    public function __construct(private readonly CurrencyService $currencies) {}

    public function index(Request $request): Response
    {
        return Inertia::render('currencies/index', [
            'currencies' => array_map(
                fn (Currency $c): array => $this->resource($c)->resolve($request),
                $this->currencies->all(),
            ),
            'base' => $this->currencies->baseCode(),
            'rates_updated_at' => $this->currencies->ratesUpdatedAt()?->format(DATE_ATOM),
            'rate_source' => $this->currencies->rateSourceName(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('currencies/create', ['base' => $this->currencies->baseCode()]);
    }

    public function store(StoreCurrencyRequest $request): RedirectResponse
    {
        try {
            $currency = $this->currencies->create($request->toInput());
        } catch (CurrencyChangeRejected $e) {
            throw ValidationException::withMessages([$e->field => $e->getMessage()]);
        }
        Log::info('Admin created a currency', ['code' => $currency->code, 'admin_id' => $request->user()?->id]);
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$currency->code} added."]);

        return redirect()->route('admin.currencies.index');
    }

    public function edit(Request $request, string $currency): Response
    {
        $found = $this->currencies->find($currency) ?? throw new CurrencyNotFound($currency);

        return Inertia::render('currencies/edit', [
            'currency' => $this->resource($found)->resolve($request),
            'base' => $this->currencies->baseCode(),
        ]);
    }

    public function update(UpdateCurrencyRequest $request, string $currency): RedirectResponse
    {
        try {
            $this->currencies->update($currency, $request->toInput($currency));
        } catch (CurrencyChangeRejected $e) {
            throw ValidationException::withMessages([$e->field => $e->getMessage()]);
        }
        Log::info('Admin updated a currency', ['code' => $currency, 'admin_id' => $request->user()?->id]);
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$currency} saved."]);

        return redirect()->route('admin.currencies.index');
    }

    public function makeDefault(Request $request, string $currency): RedirectResponse
    {
        try {
            $this->currencies->makeDefault($currency);
            Log::info('Admin changed the default currency', ['code' => $currency, 'admin_id' => $request->user()?->id]);
            Inertia::flash('toast', ['type' => 'success', 'message' => "{$currency} is now the default currency."]);
        } catch (CurrencyChangeRejected $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return redirect()->back();
    }

    public function destroy(Request $request, string $currency): RedirectResponse
    {
        try {
            $this->currencies->delete($currency);
            Log::info('Admin deleted a currency', ['code' => $currency, 'admin_id' => $request->user()?->id]);
            Inertia::flash('toast', ['type' => 'success', 'message' => "{$currency} deleted."]);
        } catch (CurrencyChangeRejected $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return redirect()->route('admin.currencies.index');
    }

    /** Pull live rates (Frankfurter). On failure nothing changes and the admin sees an error toast. */
    public function sync(Request $request): RedirectResponse
    {
        Log::info('Admin requested a live rate sync', ['admin_id' => $request->user()?->id]);

        try {
            $result = $this->currencies->syncRates();
        } catch (RatesUnavailable $e) {
            Log::warning('Live currency rates unavailable', ['reason' => $e->getMessage()]);
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Live rates unavailable; rates unchanged. Try again later.']);

            return redirect()->back();
        }

        $source = $this->currencies->rateSourceName();
        $message = $result->updated === 1
            ? "1 rate updated from {$source}."
            : "{$result->updated} rates updated from {$source}.";
        if ($result->missing !== []) {
            $message .= ' No live rate for '.implode(', ', $result->missing).' (kept as is).';
        }
        Inertia::flash('toast', ['type' => $result->missing === [] ? 'success' : 'warning', 'message' => $message]);

        return redirect()->back();
    }

    private function resource(Currency $currency): CurrencyResource
    {
        return new CurrencyResource($currency, $this->currencies->baseCode());
    }
}
