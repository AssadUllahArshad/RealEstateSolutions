<?php

namespace App\Http\Controllers;

use App\Domain\CRM\Customers;
use App\Models\CustomerDocument;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class CustomerDocumentController extends Controller
{
    public const KINDS = ['customer_photo', 'cnic_copy', 'signed_booking_form', 'signed_transfer_form', 'payment_evidence', 'other'];

    public function store(Request $request, Customers $customers, int $customer): RedirectResponse
    {
        Gate::authorize('customers.manage');
        $customers->query($request->user())->findOrFail($customer);
        $request->validate(['kind' => ['required', Rule::in(self::KINDS)],
            'file' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,pdf', 'mimetypes:image/jpeg,image/png,application/pdf'],
            'customer_id' => ['prohibited'], 'uploaded_by' => ['prohibited'], 'path' => ['prohibited'],
        ]);
        $file = $request->file('file');
        abort_unless($file instanceof UploadedFile, 422);
        if ($request->input('kind') === 'customer_photo') {
            $request->validate(['file' => ['image', 'mimes:jpg,jpeg,png', 'dimensions:max_width=10000,max_height=10000']]);
        }
        $path = $file->store((string) $customer, 'customer_documents');
        if (! is_string($path)) {
            throw new RuntimeException('Customer document could not be stored.');
        }
        try {
            DB::transaction(function () use ($request, $customers, $customer, $file, $path) {
                $actor = User::query()->lockForUpdate()->findOrFail($request->user()->id);
                Gate::forUser($actor)->authorize('customers.manage');
                $customers->query($actor)->lockForUpdate()->findOrFail($customer);
                $document = CustomerDocument::create(['customer_id' => $customer, 'uploaded_by' => $actor->id, 'kind' => $request->input('kind'),
                    'path' => $path, 'mime' => $file->getMimeType(), 'bytes' => $file->getSize()]);
                $customers->activity($actor, $customer, 'document.uploaded', ['document_id' => $document->id, 'kind' => $document->kind]);
            }, attempts: 3);
        } catch (Throwable $exception) {
            Storage::disk('customer_documents')->delete($path);
            throw $exception;
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Private customer document uploaded.']);

        return to_route('customers.show', $customer);
    }

    public function download(Request $request, Customers $customers, int $customer, int $document): StreamedResponse
    {
        $customers->query($request->user())->findOrFail($customer);
        $record = CustomerDocument::where('customer_id', $customer)->findOrFail($document);
        abort_unless(Storage::disk('customer_documents')->exists($record->path), 404);
        $customers->activity($request->user(), $customer, 'document.downloaded', ['document_id' => $record->id, 'kind' => $record->kind]);
        $extension = match ($record->mime) {
            'image/jpeg' => 'jpg', 'image/png' => 'png', default => 'pdf'
        };

        return Storage::disk('customer_documents')->download($record->path, $record->kind.'-'.$record->id.'.'.$extension, [
            'Content-Type' => $record->mime, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }
}
