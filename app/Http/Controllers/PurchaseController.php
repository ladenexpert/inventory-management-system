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

class PurchaseController extends Controller
{
    protected PurchaseService $service;

    public function __construct(PurchaseService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        return view('purchases.index');
    }

    public function create()
    {
        return view('purchases.create', [
            'purchase' => new Purchase(),
            'statuses' => PurchaseStatus::cases(),
        ]);
    }

    public function store(StorePurchaseRequest $request)
    {
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

            $targetRoute = $request->input('context') === 'material_receipt'
                ? 'material-receipts.show'
                : 'purchases.show';

            return redirect()->route($targetRoute, $purchase)
                ->with('success', 'Purchase created successfully.');

        } catch (PurchaseException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Error creating purchase: ' . $e->getMessage());
        }
    }

    public function show(Purchase $purchase)
    {
        abort_if($purchase->isMaterialReceipt(), 404);
        $purchase->load(['supplier', 'creator', 'items.product.unit', 'items.storageLocation', 'items.batch.storageLocationRecord']);
        return view('purchases.show', compact('purchase'));
    }

    public function edit(Purchase $purchase)
    {
        abort_if($purchase->isMaterialReceipt(), 404);
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

            $targetRoute = $request->input('context') === 'material_receipt'
                ? 'material-receipts.show'
                : 'purchases.show';

            return redirect()->route($targetRoute, $purchase)
                ->with('success', 'Purchase updated successfully.');

        } catch (PurchaseException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Error updating purchase: ' . $e->getMessage());
        }
    }

    public function destroy(Purchase $purchase)
    {
        // // Authorization: Only creator can delete
        // if ($purchase->created_by !== Auth::id()) {
        //     abort(403, 'You can only delete your own purchases.');
        // }

        try {
            $this->service->deletePurchase($purchase);
            return redirect()->route('purchases.index')->with('success', 'Purchase deleted successfully.');
        } catch (PurchaseException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return back()->with('error', 'Error deleting purchase: ' . $e->getMessage());
        }
    }

    public function markOrdered(Purchase $purchase)
    {
        try {
            $this->service->markAsOrdered($purchase);
            return back()->with('success', 'Purchase marked as ordered.');
        } catch (PurchaseException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return back()->with('error', 'Error marking as ordered: ' . $e->getMessage());
        }
    }

    public function markReceived(Request $request, Purchase $purchase)
    {
        $rules = [];

        if (!$purchase->isMaterialReceipt() && empty($purchase->invoice_number)) {
            $rules['invoice_number'] = 'required|string|max:255';
        }

        if (!$purchase->isMaterialReceipt() && empty($purchase->proof_image)) {
            $rules['proof_image'] = 'required|file|mimes:pdf,jpg,jpeg,png|max:2048';
        }

        $request->validate($rules);

        try {
            $updateData = [];

            if ($request->filled('invoice_number')) {
                $updateData['invoice_number'] = $request->invoice_number;
            }

            if ($request->hasFile('proof_image')) {
                $updateData['proof_image'] = $request->file('proof_image')->store('proofs', 'public');
            }

            if (!empty($updateData)) {
                $purchase->update($updateData);
            }

            $this->service->markAsReceived($purchase);

            return back()->with('success', 'Purchase received and stock updated.');

        } catch (PurchaseException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return back()->with('error', 'Error receiving purchase: ' . $e->getMessage());
        }
    }

    public function print(Purchase $purchase)
    {
        abort_if($purchase->isMaterialReceipt(), 404);

        $purchase->load(['supplier', 'creator', 'items.product.unit', 'items.storageLocation', 'items.batch.storageLocationRecord']);

        return view('purchases.print', [
            'purchase' => $purchase,
        ]);
    }

    public function cancel(Purchase $purchase)
    {
        // // Authorization: Only creator can cancel
        // if ($purchase->created_by !== Auth::id()) {
        //     abort(403, 'You can only cancel your own purchases.');
        // }

        try {
            $this->service->cancelPurchase($purchase);
            return back()->with('success', 'Purchase order cancelled.');
        } catch (PurchaseException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return back()->with('error', 'Error cancelling purchase: ' . $e->getMessage());
        }
    }

    public function markPaid(Purchase $purchase)
    {
        try {
            $this->service->markAsPaid($purchase);
            return back()->with('success', 'Purchase marked as paid.');
        } catch (PurchaseException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return back()->with('error', 'Error marking as paid: ' . $e->getMessage());
        }
    }

    public function restoreToDraft(Purchase $purchase)
    {
        // // Authorization: Only creator can restore
        // if ($purchase->created_by !== Auth::id()) {
        //     abort(403, 'You can only restore your own purchases.');
        // }

        try {
            $this->service->restoreToDraft($purchase);
            return back()->with('success', 'Purchase restored to draft.');
        } catch (PurchaseException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return back()->with('error', 'Error restoring purchase: ' . $e->getMessage());
        }
    }
}
