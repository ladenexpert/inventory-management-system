<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\DTOs\SaleData;
use Illuminate\Http\Request;
use App\Services\SaleService;
use App\Exceptions\SaleException;
use App\Enums\SaleTransactionType;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\StoreSaleRequest;
use Throwable;

class SalesController extends Controller
{
    public function index()
    {
        return view('sales.index');
    }

    public function create()
    {
        return view('sales.create');
    }

    public function store(StoreSaleRequest $request, SaleService $saleService)
    {
        try {
            $validated = $request->validated();
            $validated['created_by'] = Auth::id();
            $validated['transaction_type'] = SaleTransactionType::SALE->value;
            $validated['issued_by'] = Auth::id();

            $saleData = SaleData::fromArray($validated);

            $sale = $saleService->createSale($saleData);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Sale created successfully.',
                    'data' => $sale,
                    'print_url' => route('sales.print', $sale),
                    'redirect_url' => route('sales.show', $sale),
                ], 201);
            }

            return redirect()->route('sales.show', $sale)
                ->with('success', 'Sale created successfully.');

        } catch (SaleException $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }
            return back()->with('error', $e->getMessage())->withInput();

        } catch (Throwable $e) {
            report($e);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create sale. Please try again.',
                ], 500);
            }
            return back()->with('error', 'Failed to create sale. Please try again.')->withInput();
        }
    }

    public function show(Sale $sale)
    {
        abort_unless($sale->transaction_type === SaleTransactionType::SALE, 404);

        // // Authorization: Only creator can view
        // if ($sale->created_by !== Auth::id()) {
        //     abort(403, 'You can only view your own sales.');
        // }

        $sale->load(['items.product.unit', 'items.saleItemBatches.batch', 'customer', 'creator', 'issuer']);
        return view('sales.show', [
            'sale' => $sale,
            'context' => 'sale',
            'indexRoute' => 'sales.index',
            'printRoute' => 'sales.print',
            'completeRoute' => 'sales.complete',
            'destroyRoute' => 'sales.destroy',
            'restoreRoute' => 'sales.restore',
        ]);
    }

    public function destroy(Request $request, Sale $sale, SaleService $saleService)
    {
        abort_unless($sale->transaction_type === SaleTransactionType::SALE, 404);

        // // Authorization: Only creator can cancel
        // if ($sale->created_by !== Auth::id()) {
        //     abort(403, 'You can only cancel your own sales.');
        // }

        try {
            $sale = $saleService->cancelSale($sale, $request->input('reason'));
            return redirect()->route('sales.index')->with('success', 'Sale cancelled successfully.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function print(Sale $sale)
    {
        abort_unless($sale->transaction_type === SaleTransactionType::SALE, 404);

        // // Authorization: Only creator can print
        // if ($sale->created_by !== Auth::id()) {
        //     abort(403, 'You can only print your own sales.');
        // }

        $sale->load(['items.product.unit', 'items.saleItemBatches.batch', 'customer', 'creator', 'issuer']);
        return view('sales.print', [
            'sale' => $sale,
            'context' => 'sale',
        ]);
    }

    public function restore(Sale $sale, SaleService $saleService)
    {
        abort_unless($sale->transaction_type === SaleTransactionType::SALE, 404);

        // // Authorization: Only creator can restore
        // if ($sale->created_by !== Auth::id()) {
        //     abort(403, 'You can only restore your own sales.');
        // }

        try {
            $sale = $saleService->restoreSale($sale);
            return redirect()->route('sales.show', $sale)->with('success', 'Sale restored to pending.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function complete(Request $request, Sale $sale, SaleService $saleService)
    {
        abort_unless($sale->transaction_type === SaleTransactionType::SALE, 404);

        try {
            $paymentData = $request->only(['cash_received', 'change']);

            $sale = $saleService->completeSale($sale, $paymentData);

            return redirect()->route('sales.show', $sale)->with('success', 'Sale marked as completed.');

        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
