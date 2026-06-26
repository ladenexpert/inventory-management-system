<?php

namespace App\Http\Controllers;

use App\DTOs\SaleData;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Enums\SaleTransactionType;
use App\Exceptions\SaleException;
use App\Models\Sale;
use App\Models\Team;
use App\Services\SaleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class MaterialUsageController extends Controller
{
    public function index()
    {
        abort_unless(Auth::user()?->hasPermission('material_usage', 'view'), Response::HTTP_FORBIDDEN);

        return view('material-usages.index');
    }

    public function create()
    {
        abort_unless(Auth::user()?->hasPermission('material_usage', 'create'), Response::HTTP_FORBIDDEN);

        return view('material-usages.create', [
            'teams' => Team::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
        ]);
    }

    public function store(Request $request, SaleService $saleService)
    {
        abort_unless(Auth::user()?->hasPermission('material_usage', 'create'), Response::HTTP_FORBIDDEN);

        $validated = $request->validate([
            'usage_date' => ['required', 'date'],
            'invoice_number' => ['nullable', 'string', 'max:255'],
            'purpose' => ['required', 'string', 'max:255'],
            'formula' => ['nullable', 'string', 'max:255'],
            'team_id' => ['required', 'exists:teams,id'],
            'requested_by' => ['required', 'string', 'max:255'],
            'issued_by' => ['required', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
            'status' => ['nullable', Rule::enum(SaleStatus::class)],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'items.*.batch_allocations' => ['nullable', 'array'],
            'items.*.batch_allocations.*.batch_id' => ['required_with:items.*.batch_allocations', 'exists:batches,id'],
            'items.*.batch_allocations.*.quantity' => ['required_with:items.*.batch_allocations', 'integer', 'min:1'],
        ]);

        try {
            $validated['created_by'] = Auth::id();
            $validated['sale_date'] = $validated['usage_date'];
            $validated['payment_method'] = PaymentMethod::TRANSFER->value;
            $validated['cash_received'] = 0;
            $validated['change'] = 0;
            $validated['global_discount'] = 0;
            $validated['customer_id'] = null;
            $validated['transaction_type'] = SaleTransactionType::MATERIAL_USAGE->value;
            $validated['status'] = $validated['status'] ?? SaleStatus::COMPLETED->value;
            $validated['items'] = array_map(function (array $item) {
                $item['unit_price'] = (int) ($item['unit_price'] ?? 0);
                $item['discount'] = (int) ($item['discount'] ?? 0);

                return $item;
            }, $validated['items']);

            $usage = $saleService->createSale(SaleData::fromArray($validated));

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Material usage created successfully.',
                    'data' => $usage,
                    'print_url' => route('material-usages.print', $usage),
                    'redirect_url' => route('material-usages.show', $usage),
                ], 201);
            }

            return redirect()
                ->route('material-usages.show', $usage)
                ->with('success', 'Material usage created successfully.');
        } catch (SaleException $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            return back()->withInput()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            report($e);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create material usage. Please try again.',
                ], 500);
            }

            return back()->withInput()->with('error', 'Failed to create material usage. Please try again.');
        }
    }

    public function show(Sale $sale)
    {
        abort_unless($sale->transaction_type === SaleTransactionType::MATERIAL_USAGE, 404);
        abort_unless(Auth::user()?->hasPermission('material_usage', 'view'), Response::HTTP_FORBIDDEN);

        $sale->load(['items.product.unit', 'items.saleItemBatches.batch', 'creator', 'issuer', 'team']);

        return view('sales.show', [
            'sale' => $sale,
            'context' => 'material_usage',
            'indexRoute' => 'material-usages.index',
            'printRoute' => 'material-usages.print',
            'completeRoute' => 'material-usages.complete',
            'destroyRoute' => 'material-usages.destroy',
            'restoreRoute' => 'material-usages.restore',
        ]);
    }

    public function print(Sale $sale)
    {
        abort_unless($sale->transaction_type === SaleTransactionType::MATERIAL_USAGE, 404);
        abort_unless(Auth::user()?->hasPermission('material_usage', 'view'), Response::HTTP_FORBIDDEN);

        $sale->load(['items.product.unit', 'items.saleItemBatches.batch', 'creator', 'issuer', 'team']);

        return view('sales.print', [
            'sale' => $sale,
            'context' => 'material_usage',
        ]);
    }

    public function destroy(Request $request, Sale $sale, SaleService $saleService)
    {
        abort_unless($sale->transaction_type === SaleTransactionType::MATERIAL_USAGE, 404);
        $this->authorizeUsageMutation($sale, 'cancel');

        try {
            $saleService->cancelSale($sale, $request->input('reason'));

            return redirect()
                ->route('material-usages.index')
                ->with('success', 'Material usage cancelled successfully.');
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function restore(Sale $sale, SaleService $saleService)
    {
        abort_unless($sale->transaction_type === SaleTransactionType::MATERIAL_USAGE, 404);
        $this->authorizeUsageMutation($sale, 'restore');

        try {
            $sale = $saleService->restoreSale($sale);

            return redirect()->route('material-usages.show', $sale)->with('success', 'Material usage restored to pending.');
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function complete(Request $request, Sale $sale, SaleService $saleService)
    {
        abort_unless($sale->transaction_type === SaleTransactionType::MATERIAL_USAGE, 404);
        $this->authorizeUsageMutation($sale, 'confirm');

        try {
            $sale = $saleService->completeSale($sale, []);

            return redirect()->route('material-usages.show', $sale)->with('success', 'Material usage marked as completed.');
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    protected function authorizeUsageMutation(Sale $sale, string $action): void
    {
        $user = Auth::user();

        abort_unless($user?->hasPermission('material_usage', $action), Response::HTTP_FORBIDDEN);

        if ($user?->isRmDesk() && $sale->created_by !== $user->id) {
            abort(Response::HTTP_FORBIDDEN, 'You are only allowed to manage your own material usage records.');
        }
    }
}
