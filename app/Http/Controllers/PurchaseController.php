<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\DTOs\PurchaseData;
use Illuminate\Http\Request;
use App\Enums\PurchaseStatus;
use App\Services\PurchaseService;
use Illuminate\Support\Facades\Auth;
use App\Exceptions\PurchaseException;
use App\Http\Requests\StorePurchaseRequest;
use App\Http\Requests\UpdatePurchaseRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class PurchaseController extends Controller
{
    protected PurchaseService $service;

    public function __construct(PurchaseService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        abort_unless(auth()->user()?->hasPermission('legacy_purchase', 'view'), Response::HTTP_FORBIDDEN);

        return view('purchases.index');
    }

    public function create()
    {
        abort_unless(auth()->user()?->hasPermission('legacy_purchase', 'create'), Response::HTTP_FORBIDDEN);

        return view('purchases.create', [
            'purchase' => new Purchase(),
            'statuses' => PurchaseStatus::cases(),
        ]);
    }

    public function store(StorePurchaseRequest $request)
    {
        $this->authorizePurchaseContextAction(
            $request->input('context') === 'material_receipt' ? 'material_receipt' : 'legacy_purchase',
            'create',
        );

        try {
            $proofPath = null;
            if ($request->hasFile('proof_image')) {
                $proofPath = $request->file('proof_image')->store('proofs', 'public');
            }

            $data = $request->validated();
            $data['proof_image'] = $proofPath;
            $data['entry_context'] = $request->input('context') === 'material_receipt'
                ? 'material_receipt'
                : 'legacy_purchase';
            $data['status'] = PurchaseStatus::DRAFT->value; // Force Draft on Create

            $purchaseData = PurchaseData::fromArray($data);

            $purchase = $this->service->createPurchase($purchaseData, Auth::id());

            return $this->redirectToPurchaseDetail($purchase)
                ->with('success', $purchase->isMaterialReceipt()
                    ? 'Material receipt created successfully.'
                    : 'Legacy purchase created successfully.');

        } catch (PurchaseException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Error creating purchase: ' . $e->getMessage());
        }
    }

    public function show(Purchase $purchase)
    {
        abort_if($purchase->isMaterialReceipt(), 404);
        $this->authorizePurchaseContextAction('legacy_purchase', 'view');
        $purchase->load(['supplier', 'creator', 'items.product.unit', 'items.storageLocation', 'items.batch.storageLocationRecord']);
        return view('purchases.show', compact('purchase'));
    }

    public function edit(Purchase $purchase)
    {
        abort_if($purchase->isMaterialReceipt(), 404);
        $this->authorizePurchaseContextAction('legacy_purchase', 'update');
        // // Authorization: Only creator or admin can edit
        // if ($purchase->created_by !== Auth::id()) {
        //     abort(403, 'You can only edit your own purchases.');
        // }

        if (!in_array($purchase->status, [PurchaseStatus::DRAFT, PurchaseStatus::ORDERED])) {
            abort(403, 'Only draft or ordered purchases can be edited.');
        }

        // Load relationships needed for the form
        $purchase->load('items.product', 'items.storageLocation', 'supplier');

        return view('purchases.edit', [
            'purchase' => $purchase,
            'statuses' => PurchaseStatus::cases(),
        ]);
    }

    public function update(UpdatePurchaseRequest $request, Purchase $purchase)
    {
        $this->authorizePurchaseContextAction(
            $purchase->isMaterialReceipt() ? 'material_receipt' : 'legacy_purchase',
            'update',
        );

        // // Authorization: Only creator can update
        // if ($purchase->created_by !== Auth::id()) {
        //     abort(403, 'You can only update your own purchases.');
        // }

        try {
            $proofPath = $purchase->proof_image;
            if ($request->hasFile('proof_image')) {
                $proofPath = $request->file('proof_image')->store('proofs', 'public');
            }

            $data = $request->validated();
            $data['proof_image'] = $proofPath;
            $data['entry_context'] = $purchase->entry_context ?: ($request->input('context') === 'material_receipt' ? 'material_receipt' : 'legacy_purchase');
            $data['status'] = $purchase->status->value; // Preserve existing status

            $purchaseData = PurchaseData::fromArray($data);

            $this->service->updatePurchase($purchase, $purchaseData);

            $purchase->refresh();

            return $this->redirectToPurchaseDetail($purchase)
                ->with('success', $purchase->isMaterialReceipt()
                    ? 'Material receipt updated successfully.'
                    : 'Legacy purchase updated successfully.');

        } catch (PurchaseException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Error updating purchase: ' . $e->getMessage());
        }
    }

    public function destroy(Purchase $purchase)
    {
        $this->authorizePurchaseContextAction(
            $purchase->isMaterialReceipt() ? 'material_receipt' : 'legacy_purchase',
            'delete',
        );

        // // Authorization: Only creator can delete
        // if ($purchase->created_by !== Auth::id()) {
        //     abort(403, 'You can only delete your own purchases.');
        // }

        try {
            $this->service->deletePurchase($purchase);
            return $this->redirectToPurchaseIndex($purchase)->with('success', $purchase->isMaterialReceipt()
                ? 'Material receipt deleted successfully.'
                : 'Legacy purchase deleted successfully.');
        } catch (PurchaseException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return back()->with('error', 'Error deleting purchase: ' . $e->getMessage());
        }
    }

    public function markOrdered(Purchase $purchase)
    {
        $this->authorizePurchaseContextAction(
            $purchase->isMaterialReceipt() ? 'material_receipt' : 'legacy_purchase',
            'confirm',
        );

        try {
            $this->service->markAsOrdered($purchase);
            $purchase->refresh();

            return $this->redirectToPurchaseDetail($purchase)->with('success', $purchase->isMaterialReceipt()
                ? 'Material receipt marked as planned.'
                : 'Legacy purchase marked as ordered.');
        } catch (PurchaseException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return back()->with('error', 'Error marking as ordered: ' . $e->getMessage());
        }
    }

    public function markReceived(Request $request, Purchase $purchase)
    {
        $this->authorizePurchaseContextAction(
            $purchase->isMaterialReceipt() ? 'material_receipt' : 'legacy_purchase',
            'confirm',
        );

        $rules = [];

        if (!$purchase->isMaterialReceipt() && empty($purchase->invoice_number)) {
            $rules['invoice_number'] = 'required|string|max:255';
        }

        if (!$purchase->isMaterialReceipt() && empty($purchase->proof_image)) {
            $rules['proof_image'] = 'required|file|mimes:pdf,jpg,jpeg,png|max:2048';
        }

        $request->validate($rules);

        $storedProofPath = null;

        try {
            $updateData = [];

            if ($request->filled('invoice_number')) {
                $updateData['invoice_number'] = $request->invoice_number;
            }

            if ($request->hasFile('proof_image')) {
                $storedProofPath = $request->file('proof_image')->store('proofs', 'public');
                $updateData['proof_image'] = $storedProofPath;
            }

            $this->service->markAsReceived($purchase, $updateData);

            $purchase->refresh();

            return $this->redirectToPurchaseDetail($purchase)->with('success', $purchase->isMaterialReceipt()
                ? 'Material receipt confirmed and stock updated.'
                : 'Legacy purchase received and stock updated.');

        } catch (PurchaseException $e) {
            if (!empty($storedProofPath)) {
                Storage::disk('public')->delete($storedProofPath);
            }

            return back()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            if (!empty($storedProofPath)) {
                Storage::disk('public')->delete($storedProofPath);
            }

            return back()->with('error', 'Error receiving purchase: ' . $e->getMessage());
        }
    }

    public function print(Purchase $purchase)
    {
        abort_if($purchase->isMaterialReceipt(), 404);
        $this->authorizePurchaseContextAction('legacy_purchase', 'view');

        $purchase->load(['supplier', 'creator', 'items.product.unit', 'items.storageLocation', 'items.batch.storageLocationRecord']);

        return view('purchases.print', [
            'purchase' => $purchase,
        ]);
    }

    public function cancel(Purchase $purchase)
    {
        $this->authorizePurchaseContextAction(
            $purchase->isMaterialReceipt() ? 'material_receipt' : 'legacy_purchase',
            'cancel',
        );

        // // Authorization: Only creator can cancel
        // if ($purchase->created_by !== Auth::id()) {
        //     abort(403, 'You can only cancel your own purchases.');
        // }

        try {
            $this->service->cancelPurchase($purchase);
            $purchase->refresh();

            return $this->redirectToPurchaseDetail($purchase)->with('success', $purchase->isMaterialReceipt()
                ? 'Material receipt cancelled.'
                : 'Legacy purchase cancelled.');
        } catch (PurchaseException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return back()->with('error', 'Error cancelling purchase: ' . $e->getMessage());
        }
    }

    public function markPaid(Purchase $purchase)
    {
        abort_unless(auth()->user()?->hasPermission('finance', 'confirm'), Response::HTTP_FORBIDDEN);

        try {
            $this->service->markAsPaid($purchase);
            $purchase->refresh();

            return $this->redirectToPurchaseDetail($purchase)->with('success', 'Legacy purchase marked as paid.');
        } catch (PurchaseException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return back()->with('error', 'Error marking as paid: ' . $e->getMessage());
        }
    }

    public function restoreToDraft(Purchase $purchase)
    {
        $this->authorizePurchaseContextAction(
            $purchase->isMaterialReceipt() ? 'material_receipt' : 'legacy_purchase',
            'restore',
        );

        // // Authorization: Only creator can restore
        // if ($purchase->created_by !== Auth::id()) {
        //     abort(403, 'You can only restore your own purchases.');
        // }

        try {
            $this->service->restoreToDraft($purchase);
            $purchase->refresh();

            return $this->redirectToPurchaseDetail($purchase)->with('success', $purchase->isMaterialReceipt()
                ? 'Material receipt restored to draft.'
                : 'Legacy purchase restored to draft.');
        } catch (PurchaseException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return back()->with('error', 'Error restoring purchase: ' . $e->getMessage());
        }
    }

    private function redirectToPurchaseDetail(Purchase $purchase): RedirectResponse
    {
        return redirect()->route($purchase->isMaterialReceipt() ? 'material-receipts.show' : 'purchases.show', $purchase);
    }

    private function redirectToPurchaseIndex(Purchase $purchase): RedirectResponse
    {
        return redirect()->route($purchase->isMaterialReceipt() ? 'material-receipts.index' : 'purchases.index');
    }

    private function authorizePurchaseContextAction(string $context, string $action): void
    {
        abort_unless(
            auth()->user()?->hasPermission($context, $action),
            Response::HTTP_FORBIDDEN,
            'You are not authorized to access this feature.',
        );
    }
}
