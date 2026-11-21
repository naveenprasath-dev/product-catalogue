<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Catalogue\CatalogueService;
use App\Catalogue\Exceptions\EnquiryNotResendable;
use App\Catalogue\Exceptions\EnquiryNotSent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListEnquiriesRequest;
use App\Http\Resources\Admin\EnquiryResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

final class EnquiryController extends Controller
{
    public function __construct(private readonly CatalogueService $catalogue) {}

    public function index(ListEnquiriesRequest $request): Response
    {
        $status = $request->status();

        return Inertia::render('enquiries/index', [
            'enquiries' => EnquiryResource::collection($this->catalogue->enquiries($status, $request->page())),
            'filters' => ['status' => $status?->value],
        ]);
    }

    public function show(Request $request, int $enquiry): Response
    {
        $stored = $this->catalogue->enquiry($enquiry) ?? abort(404);

        return Inertia::render('enquiries/show', [
            // resolve(): the plain fields, without the resource's { data: … } wrapper.
            'enquiry' => (new EnquiryResource($stored))->resolve($request),
        ]);
    }

    /** Re-send a failed (or stuck pending) enquiry; the result is shown as a toast. Unknown id → 404 (EnquiryNotFound). */
    public function resend(Request $request, int $enquiry): RedirectResponse
    {
        // Ids only: no customer personal data in logs.
        Log::info('Admin re-send requested', ['enquiry_id' => $enquiry, 'admin_id' => $request->user()?->id]);

        try {
            $this->catalogue->resendEnquiry($enquiry);
            Inertia::flash('toast', ['type' => 'success', 'message' => 'Enquiry re-sent to the sales inbox.']);
        } catch (EnquiryNotResendable) {
            Inertia::flash('toast', ['type' => 'warning', 'message' => 'Only failed enquiries can be re-sent.']);
        } catch (EnquiryNotSent) {
            // Already marked failed and logged (id only) by CatalogueService.
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Still failing, try again later.']);
        }

        return redirect()->back();
    }
}
