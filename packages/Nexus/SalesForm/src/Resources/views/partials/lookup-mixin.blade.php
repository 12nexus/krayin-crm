{{--
    Phone lookup behaviour shared by the sales form and the Create Lead form.

    The component using it must provide:
      - data `form` holding lead_name, email, brokerage, city and state
      - optionally data `ignoreLeadId`, the lead being edited, so it is not
        reported as a duplicate of itself
--}}
@pushOnce('scripts', 'nexus-lookup-mixin')
    <script type="module">
        window.nexusLookupMixin = {
            data() {
                return {
                    // Normalized number of the last completed lookup, so we can
                    // tell when the rep has edited it since.
                    lookedUpPhone: null,

                    status: 'idle',

                    agent: null,

                    // Leads already on file for this number, so a rep is warned
                    // before opening a second one against the same person.
                    existingLeads: [],

                    // Fields the ViciDial match filled in, shown as a hint and
                    // safe to overwrite on a later lookup.
                    autofilled: {},

                    ignoreLeadId: null,
                };
            },

            computed: {
                /**
                 * Digits only, country code dropped — mirrors the server's
                 * normalization so both sides agree on what "changed" means.
                 */
                phone() {
                    let digits = (this.phoneInput || '').replace(/\D+/g, '');

                    if (digits.length === 11 && digits.startsWith('1')) {
                        digits = digits.substring(1);
                    }

                    return digits;
                },

                phoneComplete() {
                    return this.phone.length === 10;
                },

                /**
                 * Offer the button only once the number is complete and differs
                 * from whatever was last looked up.
                 */
                showFetchButton() {
                    return this.phoneComplete
                        && this.phone !== this.lookedUpPhone
                        && this.status !== 'loading';
                },

                duplicateHeading() {
                    return "@lang('sales_form::app.index.lookup.duplicate-many')"
                        .replace(':count', this.existingLeads.length);
                },

                location() {
                    if (! this.agent) {
                        return '';
                    }

                    return [this.agent.city, this.agent.state, this.agent.country]
                        .filter(Boolean)
                        .join(', ');
                },
            },

            watch: {
                phoneInput() {
                    clearTimeout(this.debounce);

                    if (! this.phoneComplete) {
                        // Anything typed after a lookup invalidates the match.
                        if (this.status === 'found' || this.status === 'notfound') {
                            this.status = 'incomplete';
                            this.agent = null;
                            this.existingLeads = [];
                        }

                        return;
                    }

                    // First complete number resolves on its own; after that the
                    // rep asks for it explicitly with the button.
                    if (this.lookedUpPhone === null) {
                        this.debounce = setTimeout(() => this.fetchDetails(), 400);
                    }
                },
            },

            mounted() {
                // A number carried over from a failed submit or an existing lead
                // is looked up straight away, so the verified record is on screen.
                if (this.phoneComplete) {
                    this.fetchDetails();
                }
            },

            methods: {
                fetchDetails() {
                    if (! this.phoneComplete) {
                        this.status = 'incomplete';

                        return;
                    }

                    this.status = 'loading';

                    this.$axios.get("{{ route('admin.sales_form.lookup') }}", {
                            params: { phone: this.phone },
                        })
                        .then(({ data }) => {
                            this.lookedUpPhone = this.phone;
                            this.existingLeads = (data.existing_leads || [])
                                .filter((lead) => lead.id !== this.ignoreLeadId);

                            if (data.found) {
                                this.agent = data.agent;
                                this.status = 'found';
                                this.applyAgent(data.agent);
                            } else {
                                this.agent = null;
                                this.status = 'notfound';
                                this.clearAutofilled();
                            }
                        })
                        .catch(() => {
                            this.status = 'notfound';
                            this.agent = null;
                            this.existingLeads = [];
                            this.clearAutofilled();
                        });
                },

                /**
                 * Fill only what the rep has not typed themselves, or what a
                 * previous lookup filled. A manual correction is never clobbered.
                 */
                applyAgent(agent) {
                    const mapping = {
                        lead_name: agent.full_name,
                        email: agent.email,
                        brokerage: agent.brokerage,
                        city: agent.city,
                        state: agent.state,
                    };

                    Object.entries(mapping).forEach(([field, value]) => {
                        if (! value || ! (field in this.form)) {
                            return;
                        }

                        const untouched = ! this.form[field] || this.autofilled[field];

                        if (untouched) {
                            this.form[field] = value;
                            this.autofilled[field] = true;
                        }
                    });
                },

                /**
                 * No match: drop values a previous match supplied so nothing
                 * stale is submitted, and let the rep type them in.
                 */
                clearAutofilled() {
                    Object.keys(this.autofilled).forEach((field) => {
                        this.form[field] = '';
                    });

                    this.autofilled = {};
                },
            },
        };
    </script>
@endPushOnce
