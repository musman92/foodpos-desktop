<script>
(function () {
    function normalizeSearchableSelectConfig(arg0, arg1, arg2, arg3) {
        if (arg0 && typeof arg0 === 'object' && !Array.isArray(arg0)) {
            return arg0;
        }

        return {
            options: Array.isArray(arg0) ? arg0 : [],
            value: arg1 ?? '',
            onChange: typeof arg2 === 'function' ? arg2 : null,
            ...(arg3 || {}),
        };
    }

    function optionSearchText(option) {
        if (!option) {
            return '';
        }

        return [
            option.label,
            option.name,
            option.code,
            option.search_text,
        ]
            .filter((value) => value != null && value !== '')
            .join(' ')
            .toLowerCase();
    }

    function optionDisplayLabel(option, showGlobalBadge) {
        if (!option) {
            return '';
        }

        const name = option.label || option.name || '';
        if (!showGlobalBadge) {
            return name;
        }

        const isGlobal = option.company_id === null || option.company_id === undefined;

        return isGlobal && name ? `${name} (G)` : name;
    }

    window.searchableSelect = function (arg0, arg1, arg2, arg3) {
        const config = normalizeSearchableSelectConfig(arg0, arg1, arg2, arg3);

        return {
            isOpen: false,
            searchQuery: '',
            selectedValue: config.value != null && config.value !== '' ? String(config.value) : '',
            highlightedIndex: -1,
            placeholder: config.placeholder || 'Search...',
            maxResults: config.maxResults ?? null,

            get showGlobalBadge() {
                if (typeof config.getShowGlobalBadge === 'function') {
                    return !!config.getShowGlobalBadge();
                }

                return !!config.showGlobalBadge;
            },

            get options() {
                const opts = typeof config.getOptions === 'function'
                    ? config.getOptions()
                    : (config.options || []);

                return Array.isArray(opts) ? opts : [];
            },

            get isDisabled() {
                return typeof config.getDisabled === 'function' ? !!config.getDisabled() : !!config.disabled;
            },

            get emptyMessage() {
                if (typeof config.getEmptyMessage === 'function') {
                    return config.getEmptyMessage();
                }

                return config.emptyMessage || 'No matches found';
            },

            init() {
                this.syncLabelFromValue();

                if (typeof config.onInit === 'function') {
                    config.onInit(this);
                }
            },

            syncLabelFromValue() {
                if (!this.selectedValue) {
                    if (!this.isOpen) {
                        this.searchQuery = '';
                    }

                    return;
                }

                const selected = this.options.find((option) => String(option.id) === String(this.selectedValue));
                if (selected) {
                    this.searchQuery = optionDisplayLabel(selected, this.showGlobalBadge);
                }
            },

            get filteredOptions() {
                let opts = this.options;
                const query = (this.searchQuery || '').toLowerCase().trim();

                if (query) {
                    opts = opts.filter((option) => optionSearchText(option).includes(query));
                }

                opts = [...opts].sort((a, b) =>
                    String(a.label || a.name || '').localeCompare(
                        String(b.label || b.name || ''),
                        undefined,
                        { sensitivity: 'base' }
                    )
                );

                if (this.maxResults) {
                    opts = opts.slice(0, this.maxResults);
                }

                return opts;
            },

            onSearchInput() {
                const selected = this.options.find((option) => String(option.id) === String(this.selectedValue));
                const label = selected ? optionDisplayLabel(selected, this.showGlobalBadge) : '';

                if (this.searchQuery !== label) {
                    this.selectedValue = '';
                    if (config.onChange) {
                        config.onChange('');
                    }
                }

                this.isOpen = true;
            },

            onBlur() {
                setTimeout(() => {
                    this.isOpen = false;
                    this.syncLabelFromValue();
                }, 200);
            },

            selectOption(id, label) {
                this.selectedValue = String(id);
                this.searchQuery = label;
                this.isOpen = false;
                this.highlightedIndex = -1;

                if (config.onChange) {
                    config.onChange(this.selectedValue);
                }
            },

            selectRow(option) {
                this.selectOption(option.id, optionDisplayLabel(option, this.showGlobalBadge));
            },

            optionLabel(option) {
                return optionDisplayLabel(option, this.showGlobalBadge);
            },

            highlightNext() {
                if (this.highlightedIndex < this.filteredOptions.length - 1) {
                    this.highlightedIndex++;
                }
            },

            highlightPrevious() {
                if (this.highlightedIndex > 0) {
                    this.highlightedIndex--;
                }
            },

            selectHighlighted() {
                if (this.highlightedIndex >= 0 && this.filteredOptions[this.highlightedIndex]) {
                    this.selectRow(this.filteredOptions[this.highlightedIndex]);
                }
            },
        };
    };

    window.categorySelect = function (parent) {
        return window.searchableSelect({
            getOptions: () => {
                const list = Array.isArray(parent?.categoryOptions) ? parent.categoryOptions : [];

                return [...list].sort((a, b) =>
                    String(a.label || a.name || '').localeCompare(
                        String(b.label || b.name || ''),
                        undefined,
                        { sensitivity: 'base' }
                    )
                );
            },
            showGlobalBadge: false,
            placeholder: 'Search by code or name…',
            emptyMessage: 'No categories found',
            value: parent.formData?.category_id || '',
            onChange: (value) => {
                if (parent.formData) {
                    parent.formData.category_id = value ? String(value) : '';
                }
            },
            onInit: (component) => {
                component.$watch(() => parent.formData?.category_id, (value) => {
                    component.selectedValue = value ? String(value) : '';
                    component.syncLabelFromValue();
                });
            },
        });
    };

    window.ingredientUnitSelect = function (parent, field = 'purchase_unit_id') {
        return window.searchableSelect({
            getOptions: () => {
                const list = Array.isArray(parent?.unitOptions) ? parent.unitOptions : [];

                return [...list].sort((a, b) =>
                    String(a.label || a.name || '').localeCompare(
                        String(b.label || b.name || ''),
                        undefined,
                        { sensitivity: 'base' }
                    )
                );
            },
            showGlobalBadge: false,
            placeholder: 'Search by code or name…',
            emptyMessage: 'No units found',
            value: parent.formData?.[field] || '',
            onChange: (value) => {
                if (parent.formData) {
                    parent.formData[field] = value ? String(value) : '';
                }
            },
            onInit: (component) => {
                component.$watch(() => parent.formData?.[field], (value) => {
                    component.selectedValue = value ? String(value) : '';
                    component.syncLabelFromValue();
                });
            },
        });
    };

    window.recipeCatalogSelect = function (parent) {
        return window.searchableSelect({
            getOptions: () => {
                const list = Array.isArray(parent?.ingredients) ? parent.ingredients : [];

                return [...list].sort((a, b) =>
                    String(a.label || a.name || '').localeCompare(
                        String(b.label || b.name || ''),
                        undefined,
                        { sensitivity: 'base' }
                    )
                );
            },
            showGlobalBadge: false,
            placeholder: 'Search ingredient…',
            emptyMessage: 'No ingredients found',
            value: '',
            onChange: (value) => {
                if (value) {
                    parent.addRecipeFromIngredient(value);
                }
            },
            onInit: (component) => {
                parent.recipePicker = component;
            },
        });
    };

    /**
     * Menu item form: pick a catalog recipe (default or per-option).
     * @param {object} parent menuItemForm Alpine scope
     * @param {object} target object that holds recipe_id (formData or variantRecipeRows row)
     * @param {string} field property name on target
     * @param {object} [extra] optional searchableSelect overrides (e.g. getDisabled)
     */
    window.menuItemRecipeSelect = function (parent, target, field = 'recipe_id', extra = {}) {
        return window.searchableSelect({
            getOptions: () => {
                const list = Array.isArray(parent?.catalogRecipes) ? parent.catalogRecipes : [];

                return [...list].sort((a, b) =>
                    String(a.label || a.name || '').localeCompare(
                        String(b.label || b.name || ''),
                        undefined,
                        { sensitivity: 'base' }
                    )
                );
            },
            showGlobalBadge: false,
            placeholder: 'Search recipe…',
            emptyMessage: 'No recipes found',
            value: target?.[field] || '',
            onChange: (value) => {
                if (target) {
                    target[field] = value ? String(value) : '';
                }
            },
            onInit: (component) => {
                component.$watch(() => target?.[field], (value) => {
                    component.selectedValue = value ? String(value) : '';
                    component.syncLabelFromValue();
                });
                component.$watch(() => (parent.catalogRecipes || []).length, () => {
                    component.syncLabelFromValue();
                });
            },
            ...extra,
        });
    };

    window.menuItemDefaultRecipeSelect = function (parent) {
        return window.menuItemRecipeSelect(parent, parent.formData, 'default_recipe_id', {
            getDisabled: () => !!parent.hasVariantOptions,
            onChange: (value) => {
                if (parent.formData) {
                    parent.formData.default_recipe_id = value ? String(value) : '';
                }
            },
        });
    };

    /**
     * Menu item form: searchable variant picker for one selectedVariants row.
     */
    window.menuItemVariantSelect = function (parent, variant, index) {
        return window.searchableSelect({
            getOptions: () => {
                const list = Array.isArray(parent?.availableVariants) ? parent.availableVariants : [];

                return list.map((v) => ({
                    id: String(v.id),
                    name: v.name || '',
                    code: v.code || '',
                    label: v.code ? `${v.code} — ${v.name}` : (v.name || String(v.id)),
                })).sort((a, b) =>
                    String(a.label || '').localeCompare(String(b.label || ''), undefined, { sensitivity: 'base' })
                );
            },
            showGlobalBadge: false,
            placeholder: 'Search variant…',
            emptyMessage: 'No variants found',
            value: variant?.variant_id || '',
            onChange: (value) => {
                const next = value ? String(value) : '';
                if (variant) {
                    variant.variant_id = next;
                }
                parent.loadVariantOptions(index, { target: { value: next } });
            },
            onInit: (component) => {
                component.$watch(() => variant?.variant_id, (value) => {
                    component.selectedValue = value ? String(value) : '';
                    component.syncLabelFromValue();
                });
                component.$watch(() => (parent.availableVariants || []).length, () => {
                    component.syncLabelFromValue();
                });
            },
        });
    };

    /**
     * Searchable select for adding purchase lines from the unified catalog.
     */
    window.purchaseCatalogSelect = function (parent) {
        return window.searchableSelect({
            getOptions: () => parent.catalog,
            showGlobalBadge: false,
            placeholder: 'Search ingredients or menu items…',
            emptyMessage: 'No items found',
            value: '',
            onChange: (value) => {
                if (value) {
                    parent.addFromCatalog(value);
                }
            },
            onInit: (component) => {
                parent.catalogPicker = component;
            },
        });
    };

    /**
     * Searchable select bound to a purchase line item (ingredient or menu item).
     * @deprecated Purchase form uses purchaseCatalogSelect instead.
     */
    window.itemSearchableSelect = function (item, parent, index) {
        return window.searchableSelect({
            getOptions: () => parent.selectableItems(item.item_type),
            getShowGlobalBadge: () => item.item_type === 'ingredient',
            getDisabled: () => !item.item_type,
            getEmptyMessage: () => (!item.item_type ? 'Select a type first' : 'No items found'),
            placeholder: !item.item_type
                ? 'Select type first'
                : (item.item_type === 'ingredient' ? 'Search ingredient...' : 'Search menu item...'),
            value: item.item_id,
            onChange: (value) => {
                item.item_id = value;
                parent.handleItemChange(index);
            },
            onInit: (component) => {
                component.$watch(() => item.item_type, () => {
                    component.isOpen = false;
                    component.highlightedIndex = -1;

                    if (!component.options.some((option) => String(option.id) === String(item.item_id))) {
                        item.item_id = '';
                        component.selectedValue = '';
                        component.searchQuery = '';
                    }
                });

                component.$watch(() => item.item_id, (value) => {
                    component.selectedValue = value ? String(value) : '';
                    component.syncLabelFromValue();
                });
            },
        });
    };
})();
</script>
