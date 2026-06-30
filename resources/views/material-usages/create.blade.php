<x-app-layout title="Create Material Usage">
    <div class="mx-auto px-4 py-4 sm:px-6 lg:px-8"
        x-data="usageForm()"
        x-init="init()"
        @keydown.window.f1.prevent="productTs && productTs.focus()"
        @keydown.window.f2.prevent="issuerTs && issuerTs.focus()"
        @keydown.window.f3.prevent="openConfirmation()"
    >
        <div class="relative flex flex-col gap-4 lg:h-[calc(100vh-100px)] lg:flex-row">
            <div class="flex min-w-0 w-full flex-col gap-4 lg:w-[68%] lg:h-full">
                <div class="relative z-20 mb-2">
                    <select
                        x-ref="productSelect"
                        placeholder="Search raw material (Name, SKU, or IERP Code) [F1]..."
                        autocomplete="off"
                    ></select>
                </div>

                <div class="flex-1 bg-white rounded-lg shadow border border-gray-200 overflow-hidden flex flex-col">
                    <div class="overflow-x-auto flex-1">
                        <table class="min-w-[760px] divide-y divide-gray-200">
                            <thead class="bg-gray-50 sticky top-0 z-10">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">RM</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Avail</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Qty</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Unit</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Batch</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <template x-for="(item, index) in cart" :key="item.id + '-' + index">
                                    <tr :class="index % 2 === 0 ? 'bg-white' : 'bg-gray-50'" class="hover:bg-indigo-50 transition-colors">
                                        <td class="px-6 py-4 align-top">
                                            <div class="text-sm font-medium break-words text-gray-900" x-text="item.name"></div>
                                            <div class="text-xs text-gray-500">
                                                <span x-text="item.item_code_ierp || '-'"></span>
                                                <span class="mx-1">|</span>
                                                <span x-text="item.sku"></span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-500">
                                            <span x-text="item.max_stock"></span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center">
                                            <div class="flex items-center justify-center">
                                                <input type="number"
                                                    x-model="item.quantity"
                                                    min="1"
                                                    :max="item.max_stock"
                                                    @input="validateQty(index)"
                                                    class="w-20 text-center border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500 text-sm shadow-sm"
                                                    placeholder="1">
                                            </div>
                                            <div x-show="hasError(`items.${index}.quantity`)" x-text="getError(`items.${index}.quantity`)" class="text-xs text-red-600 mt-1"></div>
                                            <div x-show="item.quantity > item.max_stock" class="text-xs text-red-600 mt-1">
                                                Max: <span x-text="item.max_stock"></span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-500" x-text="item.unit"></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center">
                                            <button @click="toggleBatchMode(index)"
                                                class="text-xs font-medium px-2 py-1 rounded"
                                                :class="item.use_manual_batch ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-600'">
                                                <span x-text="item.use_manual_batch ? 'Manual Pick' : 'FEFO'"></span>
                                            </button>
                                            <div x-show="item.use_manual_batch && item.batch_select_open" class="mt-2 text-left">
                                                <div class="text-xs text-gray-500 mb-1">Select batches:</div>
                                                <div x-show="item.is_loading_batches" class="text-xs text-gray-500 mb-2">Loading available batches...</div>
                                                <div x-show="item.batch_load_error" x-text="item.batch_load_error" class="text-xs text-red-600 mb-2"></div>
                                                <div x-show="!item.is_loading_batches && !item.batch_load_error && item.available_batches.length === 0" class="text-xs text-amber-600 mb-2">
                                                    No usable batch is available for this raw material yet. Receive or adjust stock first.
                                                </div>
                                                <template x-for="(batch, batchIndex) in item.available_batches" :key="batch.id">
                                                    <div class="flex items-center justify-between text-xs p-2 rounded mb-1"
                                                        :class="batch.can_be_consumed ? 'bg-gray-50' : 'bg-red-50 border border-red-100'">
                                                        <div>
                                                            <span x-text="batch.batch_number"></span>
                                                            <span class="ml-1 text-gray-500">
                                                                (<span x-text="batch.expiry_formatted"></span>)
                                                            </span>
                                                            <span class="ml-1 text-gray-400">
                                                                Avail: <span x-text="batch.available_quantity"></span>
                                                            </span>
                                                            <span class="ml-1 text-gray-400">
                                                                Loc: <span x-text="batch.storage_location_label || '-'"></span>
                                                            </span>
                                                            <span class="ml-1"
                                                                :class="batch.can_be_consumed ? 'text-sky-600' : 'text-red-600'"
                                                                x-text="batch.status_label"></span>
                                                        </div>
                                                        <input type="number"
                                                            x-model="item.batch_allocations[batchIndex].quantity"
                                                            min="0"
                                                            :max="batch.available_quantity"
                                                            class="w-16 text-center border-gray-300 rounded text-xs py-1 disabled:bg-gray-100 disabled:text-gray-400"
                                                            :disabled="!batch.can_be_consumed"
                                                            @input="validateBatchQty(index, batchIndex)">
                                                    </div>
                                                    <div x-show="hasError(`items.${index}.batch_allocations.${batchIndex}.quantity`)" x-text="getError(`items.${index}.batch_allocations.${batchIndex}.quantity`)" class="text-xs text-red-600 mb-1"></div>
                                                </template>
                                                <div class="text-xs mt-1" :class="getBatchTotal(index) === item.quantity ? 'text-green-600' : 'text-red-600'">
                                                    Allocated: <span x-text="getBatchTotal(index)"></span> / <span x-text="item.quantity"></span>
                                                </div>
                                                <div x-show="hasError(`items.${index}.batch_allocations`)" x-text="getError(`items.${index}.batch_allocations`)" class="text-xs text-red-600 mt-1"></div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                            <button @click="removeFromCart(index)" class="flex items-center justify-center w-8 h-8 rounded-full bg-red-100 text-red-600 hover:bg-red-200 focus:outline-none transition-colors mx-auto">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                                <template x-if="cart.length === 0">
                                    <tr>
                                        <td colspan="6" class="px-6 py-20 text-center text-gray-500">
                                            <div class="flex flex-col items-center justify-center">
                                                <svg class="w-12 h-12 text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                                <p class="text-base font-medium">No raw material selected</p>
                                                <p class="text-sm text-gray-400">Search inventory above to begin a usage slip</p>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="flex w-full flex-col rounded-lg border border-gray-200 bg-white shadow lg:h-full lg:w-[32%]">
                <div class="p-4 border-b border-gray-200 bg-gray-50 rounded-t-lg">
                    <h2 class="text-xs font-bold text-gray-500 uppercase tracking-wide">Usage Details</h2>
                </div>

                <div class="flex-1 space-y-4 overflow-y-auto p-4">
                    <div x-show="errorSummary.length" x-cloak class="rounded-lg border border-red-200 bg-red-50 p-3">
                        <div class="text-sm font-semibold text-red-700">Please review the highlighted fields.</div>
                        <template x-for="(message, index) in errorSummary" :key="`error-${index}`">
                            <div class="mt-1 text-sm text-red-600" x-text="message"></div>
                        </template>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-xs font-bold text-gray-500 uppercase">Usage Date</label>
                        <input type="date" x-model="form.usage_date" class="block w-full rounded-md border-gray-300 focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                        <div x-show="hasError('usage_date')" x-text="getError('usage_date')" class="text-xs text-red-600"></div>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-xs font-bold text-gray-500 uppercase">Purpose</label>
                        <input type="text" x-model="form.purpose" class="block w-full rounded-md border-gray-300 focus:ring-indigo-500 focus:border-indigo-500 text-sm" placeholder="Example: Pilot batch production">
                        <div x-show="hasError('purpose')" x-text="getError('purpose')" class="text-xs text-red-600"></div>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-xs font-bold text-gray-500 uppercase">Reference</label>
                        <input type="text" x-model="form.invoice_number" class="block w-full rounded-md border-gray-300 focus:ring-indigo-500 focus:border-indigo-500 text-sm" placeholder="Optional external reference">
                        <div x-show="hasError('invoice_number')" x-text="getError('invoice_number')" class="text-xs text-red-600"></div>
                    </div>

                    <div class="grid grid-cols-1 gap-4">
                        <div class="space-y-2">
                            <label class="block text-xs font-bold text-gray-500 uppercase">Formula</label>
                            <input type="text" x-model="form.formula" class="block w-full rounded-md border-gray-300 focus:ring-indigo-500 focus:border-indigo-500 text-sm" placeholder="Optional">
                            <div x-show="hasError('formula')" x-text="getError('formula')" class="text-xs text-red-600"></div>
                        </div>

                        <div class="space-y-2">
                            <label class="block text-xs font-bold text-gray-500 uppercase">Team</label>
                            <select x-model="form.team_id" class="block w-full rounded-md border-gray-300 focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                                <option value="">Select team</option>
                                @foreach($teams as $team)
                                    <option value="{{ $team->id }}">{{ $team->name }} ({{ $team->code }})</option>
                                @endforeach
                            </select>
                            <div x-show="hasError('team_id')" x-text="getError('team_id')" class="text-xs text-red-600"></div>
                        </div>

                        <div class="space-y-2">
                            <label class="block text-xs font-bold text-gray-500 uppercase">Requested By</label>
                            <input type="text" x-model="form.requested_by" class="block w-full rounded-md border-gray-300 focus:ring-indigo-500 focus:border-indigo-500 text-sm" placeholder="Required">
                            <div x-show="hasError('requested_by')" x-text="getError('requested_by')" class="text-xs text-red-600"></div>
                        </div>

                        <div class="space-y-2">
                            <label class="block text-xs font-bold text-gray-500 uppercase">Issued By</label>
                            <select x-ref="issuerSelect" placeholder="Search issuer [F2]..." autocomplete="off"></select>
                            <div x-show="hasError('issued_by')" x-text="getError('issued_by')" class="text-xs text-red-600"></div>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-xs font-bold text-gray-500 uppercase">Notes</label>
                        <textarea x-model="form.notes" rows="4" class="block w-full text-sm border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500 placeholder-gray-400 py-2" placeholder="Optional notes for this usage slip..."></textarea>
                        <div x-show="hasError('notes')" x-text="getError('notes')" class="text-xs text-red-600"></div>
                    </div>

                    <div class="bg-indigo-50 rounded-lg p-4 border border-indigo-100 space-y-3">
                        <div class="flex justify-between items-center text-sm">
                            <span class="font-medium text-gray-600">Lines</span>
                            <span class="font-semibold text-gray-900" x-text="cart.length"></span>
                        </div>
                        <div class="flex justify-between items-center text-sm">
                            <span class="font-medium text-gray-600">Total Qty</span>
                            <span class="font-semibold text-gray-900" x-text="totalQuantity"></span>
                        </div>
                        <div class="flex justify-between items-center text-sm">
                            <span class="font-medium text-gray-600">FEFO Lines</span>
                            <span class="font-semibold text-gray-900" x-text="cart.filter(item => !item.use_manual_batch).length"></span>
                        </div>
                        <div class="flex justify-between items-center text-sm">
                            <span class="font-medium text-gray-600">Manual Pick Lines</span>
                            <span class="font-semibold text-gray-900" x-text="cart.filter(item => item.use_manual_batch).length"></span>
                        </div>
                    </div>
                </div>

                <div class="sticky bottom-0 flex gap-3 border-t border-gray-200 bg-gray-50 p-4">
                    <button
                        @click="resetForm()"
                        class="flex w-1/3 items-center justify-center rounded-lg border border-red-200 bg-white py-3 text-sm font-bold text-red-600 shadow-sm transition-colors hover:bg-red-600 hover:text-white"
                    >
                        Reset
                    </button>

                    <button
                        @click="openConfirmation()"
                        :disabled="isSubmitting || cart.length === 0"
                        class="w-2/3 flex justify-center items-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-lg font-bold text-white bg-blue-600 hover:bg-blue-700 focus:outline-none disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >
                        <span x-text="isSubmitting ? 'Processing...' : 'ISSUE MATERIAL (F3)'"></span>
                    </button>
                </div>
            </div>
        </div>

        <script>
            function usageForm() {
                return {
                    cart: [],
                    form: {
                        usage_date: @js(now()->format('Y-m-d')),
                        invoice_number: '',
                        purpose: '',
                        formula: '',
                        team_id: '',
                        requested_by: '',
                        issued_by: '{{ auth()->id() }}',
                        notes: ''
                    },
                    issuer: { id: '{{ auth()->id() }}', name: '{{ addslashes(auth()->user()->name) }}' },
                    teamOptions: @js($teams->map(fn ($team) => [
                        'id' => (string) $team->id,
                        'label' => "{$team->name} ({$team->code})",
                    ])->values()),
                    isSubmitting: false,
                    errors: {},
                    errorSummary: [],
                    productTs: null,
                    issuerTs: null,

                    init() {
                        this.initProductSelect();
                        this.initIssuerSelect();
                    },

                    get totalQuantity() {
                        return this.cart.reduce((sum, item) => sum + (parseInt(item.quantity) || 0), 0);
                    },

                    selectedTeamLabel() {
                        return this.teamOptions.find(team => team.id === String(this.form.team_id))?.label || '-';
                    },

                    hasError(field) {
                        return Array.isArray(this.errors[field]) && this.errors[field].length > 0;
                    },

                    getError(field) {
                        return this.hasError(field) ? this.errors[field][0] : '';
                    },

                    clearErrors() {
                        this.errors = {};
                        this.errorSummary = [];
                    },

                    setValidationErrors(errors) {
                        this.errors = errors || {};
                        this.errorSummary = Object.values(this.errors)
                            .flat()
                            .filter((message, index, array) => array.indexOf(message) === index);
                    },

                    initProductSelect() {
                        if (!this.$refs.productSelect) return;

                        this.productTs = new TomSelect(this.$refs.productSelect, {
                            placeholder: 'Search raw material...',
                            preload: 'focus',
                            valueField: 'value',
                            labelField: 'text',
                            searchField: 'text',
                            load: (query, callback) => {
                                fetch('{{ route('ajax.products.search') }}', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                                    },
                                    body: JSON.stringify({ q: query })
                                })
                                    .then(response => response.json())
                                    .then(json => callback(json))
                                    .catch(() => callback());
                            },
                            onItemAdd: (value) => {
                                const product = this.productTs.options[value];
                                if (product) {
                                    this.addProduct(product);
                                }

                                this.productTs.clear(true);
                                this.productTs.focus();
                            }
                        });
                    },

                    initIssuerSelect() {
                        if (!this.$refs.issuerSelect) return;

                        this.issuerTs = new TomSelect(this.$refs.issuerSelect, {
                            placeholder: 'Search issuer...',
                            preload: 'focus',
                            valueField: 'value',
                            labelField: 'text',
                            searchField: 'text',
                            items: [this.form.issued_by],
                            options: [{
                                value: this.form.issued_by,
                                text: this.issuer.name
                            }],
                            load: (query, callback) => {
                                fetch('{{ route('ajax.users.search') }}', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                                    },
                                    body: JSON.stringify({ q: query })
                                })
                                    .then(response => response.json())
                                    .then(json => callback(json))
                                    .catch(() => callback());
                            },
                            onChange: (value) => {
                                this.form.issued_by = value;
                            }
                        });
                    },

                    async addProduct(product) {
                        this.cart.push({
                            id: product.id,
                            name: product.name,
                            sku: product.sku,
                            item_code_ierp: product.item_code_ierp || '-',
                            unit: product.unit?.symbol || product.unit?.name || '-',
                            max_stock: parseInt(product.quantity || 0),
                            quantity: 1,
                            discount: 0,
                            use_manual_batch: true,
                            batch_select_open: true,
                            is_loading_batches: false,
                            batch_load_error: '',
                            available_batches: [],
                            batch_allocations: []
                        });

                        await this.loadBatchesForItem(this.cart.length - 1);
                    },

                    removeFromCart(index) {
                        this.cart.splice(index, 1);
                    },

                    validateQty(index) {
                        const item = this.cart[index];
                        item.quantity = parseInt(item.quantity || 0);

                        if (item.quantity < 1) item.quantity = 1;
                        if (item.quantity > item.max_stock) item.quantity = item.max_stock;

                        if (item.use_manual_batch) {
                            this.normalizeBatchAllocations(index);
                        }
                    },

                    buildBatchAllocations(batches, existingAllocations = []) {
                        const existingMap = new Map(
                            (existingAllocations || []).map(allocation => [allocation.batch_id, parseInt(allocation.quantity || 0)])
                        );

                        return batches.map(batch => ({
                            batch_id: batch.id,
                            quantity: Math.min(existingMap.get(batch.id) || 0, batch.available_quantity)
                        }));
                    },

                    async loadBatchesForItem(index) {
                        const item = this.cart[index];

                        if (!item) {
                            return;
                        }

                        item.is_loading_batches = true;
                        item.batch_load_error = '';

                        try {
                            const response = await fetch('{{ route('ajax.batches.get') }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                                },
                                body: JSON.stringify({ product_id: item.id })
                            });

                            const data = await response.json();

                            if (!response.ok || data?.success === false) {
                                throw new Error(data?.message || 'Failed to load available batches.');
                            }

                            item.available_batches = data.data || [];
                            item.batch_allocations = this.buildBatchAllocations(item.available_batches, item.batch_allocations);

                            if (item.available_batches.length === 0) {
                                item.batch_load_error = 'No usable batch is available for this raw material yet. Receive or adjust stock first.';
                            }
                        } catch (error) {
                            item.available_batches = [];
                            item.batch_allocations = [];
                            item.batch_load_error = error?.message || 'Unable to load available batches. Please try again.';
                            this.$dispatch('toast', { message: item.batch_load_error, type: 'error' });
                        } finally {
                            item.is_loading_batches = false;
                        }
                    },

                    async toggleBatchMode(index) {
                        const item = this.cart[index];
                        item.use_manual_batch = !item.use_manual_batch;
                        item.batch_select_open = item.use_manual_batch;

                        if (item.use_manual_batch) {
                            await this.loadBatchesForItem(index);
                            return;
                        }

                        item.batch_load_error = '';
                        item.batch_allocations = this.buildBatchAllocations(item.available_batches, []);
                    },

                    validateBatchQty(itemIndex, batchIndex) {
                        const item = this.cart[itemIndex];
                        const batch = item.available_batches[batchIndex];
                        const allocation = item.batch_allocations[batchIndex];
                        let qty = parseInt(allocation.quantity || 0);

                        if (qty < 0) qty = 0;
                        if (qty > batch.available_quantity) qty = batch.available_quantity;

                        allocation.quantity = qty;
                    },

                    normalizeBatchAllocations(index) {
                        const item = this.cart[index];
                        item.batch_allocations = item.batch_allocations.map((allocation, allocationIndex) => {
                            const batch = item.available_batches[allocationIndex];
                            const safeQty = Math.min(parseInt(allocation.quantity || 0), batch?.available_quantity || 0);

                            return {
                                ...allocation,
                                quantity: safeQty < 0 ? 0 : safeQty
                            };
                        });
                    },

                    getBatchTotal(index) {
                        return this.cart[index].batch_allocations.reduce((sum, allocation) => sum + (parseInt(allocation.quantity) || 0), 0);
                    },

                    openConfirmation() {
                        if (this.cart.length === 0) {
                            this.$dispatch('toast', { message: 'Add at least one raw material before issuing.', type: 'error' });
                            return;
                        }

                        if (!this.form.purpose.trim()) {
                            this.$dispatch('toast', { message: 'Purpose is required.', type: 'error' });
                            return;
                        }

                        if (!this.form.team_id) {
                            this.$dispatch('toast', { message: 'Team is required.', type: 'error' });
                            return;
                        }

                        if (!this.form.requested_by.trim()) {
                            this.$dispatch('toast', { message: 'Requested by is required.', type: 'error' });
                            return;
                        }

                        if (!this.form.issued_by) {
                            this.$dispatch('toast', { message: 'Issued by is required.', type: 'error' });
                            return;
                        }

                        const invalidManual = this.cart.findIndex(item => item.use_manual_batch && this.getBatchTotal(this.cart.indexOf(item)) !== parseInt(item.quantity));

                        if (invalidManual !== -1) {
                            this.$dispatch('toast', { message: 'Manual batch allocations must match the requested quantity.', type: 'error' });
                            return;
                        }

                        this.$dispatch('open-modal', { name: 'confirmation-modal' });
                    },

                    async submitUsage() {
                        if (this.isSubmitting) {
                            return;
                        }

                        this.isSubmitting = true;
                        this.clearErrors();

                        try {
                            const items = this.cart.map(item => {
                                const payload = {
                                    product_id: item.id,
                                    quantity: item.quantity,
                                    unit_price: 0,
                                    discount: 0
                                };

                                if (item.use_manual_batch) {
                                    payload.batch_allocations = item.batch_allocations.filter(allocation => parseInt(allocation.quantity) > 0);
                                }

                                return payload;
                            });

                            const payload = {
                                usage_date: this.form.usage_date,
                                invoice_number: this.form.invoice_number,
                                purpose: this.form.purpose,
                                formula: this.form.formula,
                                team_id: this.form.team_id,
                                requested_by: this.form.requested_by,
                                issued_by: this.form.issued_by,
                                notes: this.form.notes,
                                status: 'completed',
                                items,
                                _token: '{{ csrf_token() }}'
                            };

                            const response = await fetch('{{ route('material-usages.store') }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                                },
                                body: JSON.stringify(payload)
                            });

                            const isJsonResponse = (response.headers.get('content-type') || '').includes('application/json');
                            const data = isJsonResponse ? await response.json() : null;

                            if (response.ok && data?.success) {
                                this.$dispatch('close-modal', { name: 'confirmation-modal' });
                                window.location.href = data.redirect_url || '{{ route('material-usages.index') }}';
                                return;
                            }

                            this.$dispatch('close-modal', { name: 'confirmation-modal' });

                            if (response.status === 422 && data?.errors) {
                                this.setValidationErrors(data.errors);
                                this.$dispatch('toast', { message: data.message || 'Please review the highlighted fields.', type: 'error' });
                                return;
                            }

                            this.$dispatch('toast', { message: data?.message || 'Material usage could not be created. Please review the slip and try again.', type: 'error' });
                        } catch (error) {
                            console.error(error);
                            this.$dispatch('toast', { message: 'Network error. Please retry the material usage submission.', type: 'error' });
                        } finally {
                            this.isSubmitting = false;
                        }
                    },

                    resetForm() {
                        this.cart = [];
                        this.clearErrors();
                        this.form = {
                            usage_date: @js(now()->format('Y-m-d')),
                            invoice_number: '',
                            purpose: '',
                            formula: '',
                            team_id: '',
                            requested_by: '',
                            issued_by: '{{ auth()->id() }}',
                            notes: ''
                        };

                        this.productTs && this.productTs.clear();

                        if (this.issuerTs) {
                            this.issuerTs.clear(true);
                            this.issuerTs.addOption({ value: '{{ auth()->id() }}', text: '{{ addslashes(auth()->user()->name) }}' });
                            this.issuerTs.setValue('{{ auth()->id() }}');
                        }
                    }
                }
            }
        </script>

        <x-modal name="confirmation-modal" focusable>
            <div class="p-6">
                <div class="mb-6 space-y-1.5 text-center sm:text-left border-b border-gray-200 pb-4">
                    <h3 class="text-lg font-semibold leading-none tracking-tight text-foreground">
                        Confirm Material Usage
                    </h3>
                    <p class="text-sm text-muted-foreground">
                        Review the usage slip details before stock is issued.
                    </p>
                </div>

                <div class="grid gap-4 py-4">
                    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                        <span class="text-sm font-medium text-gray-500">Usage Date</span>
                        <span class="font-semibold sm:text-right" x-text="form.usage_date"></span>
                    </div>
                    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                        <span class="text-sm font-medium text-gray-500">Purpose</span>
                        <span class="font-semibold break-words sm:text-right" x-text="form.purpose"></span>
                    </div>
                    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                        <span class="text-sm font-medium text-gray-500">Formula</span>
                        <span class="font-semibold break-words sm:text-right" x-text="form.formula || '-'"></span>
                    </div>
                    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                        <span class="text-sm font-medium text-gray-500">Team</span>
                        <span class="font-semibold break-words sm:text-right">
                            <span x-text="selectedTeamLabel()"></span>
                        </span>
                    </div>
                    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                        <span class="text-sm font-medium text-gray-500">Requested By</span>
                        <span class="font-semibold break-words sm:text-right" x-text="form.requested_by || '-'"></span>
                    </div>
                    <div class="mt-2 flex flex-col gap-1 border-t border-gray-100 pt-2 sm:flex-row sm:items-center sm:justify-between">
                        <span class="text-sm font-medium text-gray-500">Lines</span>
                        <span class="font-semibold sm:text-right" x-text="cart.length"></span>
                    </div>
                    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                        <span class="text-sm font-medium text-gray-500">Total Qty</span>
                        <span class="font-semibold sm:text-right" x-text="totalQuantity"></span>
                    </div>
                </div>

                <div class="mt-6 border-t border-gray-200 pt-4 space-y-4">
                    <button
                        @click="submitUsage()"
                        :disabled="isSubmitting"
                        class="w-full flex justify-center items-center py-3 px-4 rounded-lg shadow-sm text-lg font-bold text-white bg-blue-600 hover:bg-blue-700 focus:outline-none disabled:opacity-50 transition-colors"
                    >
                        <span x-text="isSubmitting ? 'Processing...' : 'ISSUE MATERIAL'"></span>
                    </button>

                    <x-secondary-button
                        type="button"
                        @click="$dispatch('close-modal', { name: 'confirmation-modal' })"
                        class="w-full justify-center"
                    >
                        Back
                    </x-secondary-button>
                </div>
            </div>
        </x-modal>
    </div>
</x-app-layout>
