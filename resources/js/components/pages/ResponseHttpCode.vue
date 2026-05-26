<template>
    <div class="cabinet-hh-bulk">
        <section class="cabinet-hh-panel card border shadow-sm" aria-labelledby="cabinet-hh-bulk-title">
            <div class="card-body">
                <h2 class="cabinet-hh-step-title h6 mb-3" id="cabinet-hh-bulk-title">
                    <span class="cabinet-hh-step-badge">1</span>
                    <span>{{ bulkStepTitle }}</span>
                </h2>

                <form @submit.prevent="ShowHttpResponse">
                    <div class="mb-3">
                        <label class="form-label fw-medium" for="cabinet-hh-urls">{{ textTitle }}</label>
                        <textarea
                            id="cabinet-hh-urls"
                            class="form-control font-monospace"
                            rows="8"
                            v-model="urls"
                            :placeholder="urlsPlaceholder"
                            :disabled="loading"
                        ></textarea>
                        <div class="form-text">{{ bulkHint }}</div>
                    </div>
                    <div class="row g-3 align-items-end">
                        <div class="col-sm-6 col-md-4">
                            <label class="form-label fw-medium" for="cabinet-hh-timeout">{{ timeoutTitle }}</label>
                            <div class="input-group">
                                <input
                                    id="cabinet-hh-timeout"
                                    type="number"
                                    min="1"
                                    max="60000"
                                    class="form-control"
                                    v-model.number="time"
                                    :disabled="loading"
                                >
                                <span class="input-group-text">мс</span>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-8">
                            <button type="submit" class="btn btn-primary" :disabled="loading || !urlsTrimmed">
                                <span v-if="loading" class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                                <i v-else class="bi bi-play-fill me-1" aria-hidden="true"></i>
                                {{ submit }}
                            </button>
                            <button
                                v-if="cardDisplay"
                                type="button"
                                class="btn btn-outline-secondary ms-2"
                                :disabled="loading"
                                @click="clearResults"
                            >
                                <i class="bi bi-x-lg me-1" aria-hidden="true"></i>{{ clearBtn }}
                            </button>
                        </div>
                    </div>
                </form>

                <div v-if="loading" class="mt-3" aria-live="polite">
                    <div class="d-flex justify-content-between small text-secondary mb-1">
                        <span>{{ progressLabel }}</span>
                        <span>{{ doneCount }} / {{ totalCount }}</span>
                    </div>
                    <div class="progress cabinet-hh-progress" role="progressbar" :aria-valuenow="progressPercent" aria-valuemin="0" aria-valuemax="100">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" :style="{ width: progressPercent + '%' }"></div>
                    </div>
                </div>
            </div>
        </section>

        <section v-if="cardDisplay" class="cabinet-hh-panel card border shadow-sm" aria-labelledby="cabinet-hh-results-title">
            <div class="card-header py-3">
                <h2 class="cabinet-hh-step-title h6 mb-2" id="cabinet-hh-results-title">
                    <span class="cabinet-hh-step-badge">2</span>
                    <span>{{ resultsTitle }}</span>
                </h2>
                <div class="cabinet-hh-codes-summary d-flex flex-wrap gap-2">
                    <span v-for="(group, code) in codes" :key="code" class="badge text-bg-secondary">
                        {{ code }}: <strong class="ms-1">{{ group.length }}</strong>
                    </span>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="row g-3 p-3 cabinet-hh-kpi">
                    <div class="col-6 col-md-3">
                        <div class="info-box shadow-sm mb-0">
                            <span class="info-box-icon text-bg-secondary"><i class="bi bi-list-ol" aria-hidden="true"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">{{ kpiTotal }}</span>
                                <span class="info-box-number">{{ items.length }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="info-box shadow-sm mb-0">
                            <span class="info-box-icon text-bg-success"><i class="bi bi-check-lg" aria-hidden="true"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">{{ kpiOk }}</span>
                                <span class="info-box-number">{{ okCount }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="info-box shadow-sm mb-0">
                            <span class="info-box-icon text-bg-danger"><i class="bi bi-exclamation-lg" aria-hidden="true"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">{{ kpiError }}</span>
                                <span class="info-box-number">{{ errorCount }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="info-box shadow-sm mb-0">
                            <span class="info-box-icon text-bg-primary"><i class="bi bi-hash" aria-hidden="true"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">{{ codeTitle }}</span>
                                <span class="info-box-number">{{ distinctCodes }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive px-3 pb-3">
                    <table class="table table-hover table-sm align-middle cabinet-hh-results-table mb-0">
                        <thead class="table-light">
                        <tr>
                            <th scope="col" style="width: 3rem">#</th>
                            <th scope="col" style="width: 3.5rem">{{ more }}</th>
                            <th scope="col">{{ urlTitle }}</th>
                            <th scope="col" class="sorting user-select-none" style="width: 6rem; cursor: pointer" @click.prevent="Sorting">
                                {{ codeTitle }}
                                <i class="bi bi-arrow-down-up ms-1 small opacity-75" aria-hidden="true"></i>
                            </th>
                            <th scope="col" style="width: 4rem" class="text-center">{{ statusTitle }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        <tr v-for="item in items" :key="item.id">
                            <td class="text-secondary">{{ item.id + 1 }}</td>
                            <td>
                                <div class="dropdown">
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-secondary"
                                        data-bs-toggle="dropdown"
                                        aria-expanded="false"
                                        :aria-label="more"
                                    >
                                        <i class="bi bi-three-dots" aria-hidden="true"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                        <li>
                                            <a class="dropdown-item" :href="detailHref(item.url)" target="_blank" rel="noopener">
                                                <i class="bi bi-box-arrow-up-right me-2" aria-hidden="true"></i>{{ openNewPage }}
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </td>
                            <td class="cabinet-hh-url-cell">{{ item.url }}</td>
                            <td>
                                <span class="badge rounded-pill" :class="item.status ? 'text-bg-success' : 'text-bg-danger'">
                                    {{ item.code }}
                                </span>
                            </td>
                            <td class="text-center">
                                <i v-if="item.status" class="bi bi-check-circle-fill text-success" :title="statusOk" aria-hidden="true"></i>
                                <i v-else class="bi bi-x-circle-fill text-danger" :title="statusFail" aria-hidden="true"></i>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</template>

<script>
export default {
    name: "ResponseHttpCode",
    props: {
        submit: { type: String, default: "Send" },
        urlTitle: { type: String, default: "URL" },
        codeTitle: { type: String, default: "Code" },
        textTitle: { type: String, default: "" },
        timeoutTitle: { type: String, default: "" },
        exportBtn: { type: String, default: "Export" },
        openNewPage: { type: String, default: "" },
        more: { type: String, default: "" },
        bulkStepTitle: { type: String, default: "" },
        bulkHint: { type: String, default: "" },
        urlsPlaceholder: { type: String, default: "" },
        resultsTitle: { type: String, default: "" },
        clearBtn: { type: String, default: "" },
        progressLabel: { type: String, default: "" },
        kpiTotal: { type: String, default: "" },
        kpiOk: { type: String, default: "" },
        kpiError: { type: String, default: "" },
        statusTitle: { type: String, default: "" },
        statusOk: { type: String, default: "" },
        statusFail: { type: String, default: "" },
    },
    data() {
        return {
            time: 1000,
            order: "desc",
            urls: "",
            arUrls: [],
            items: [],
            codes: {},
            table: {},
            loading: false,
            totalCount: 0,
            doneCount: 0,
        };
    },
    computed: {
        cardDisplay() {
            return this.items.length > 0;
        },
        urlsTrimmed() {
            return (this.urls || "").trim().length > 0;
        },
        progressPercent() {
            if (this.totalCount <= 0) return 0;
            return Math.min(100, Math.round((this.doneCount / this.totalCount) * 100));
        },
        okCount() {
            return this.items.filter((i) => i.status).length;
        },
        errorCount() {
            return this.items.filter((i) => !i.status).length;
        },
        distinctCodes() {
            return Object.keys(this.codes).length;
        },
    },
    methods: {
        ShowHttpResponse() {
            this.StringToArray();
            if (this.arUrls.length === 0) return;

            this.items = [];
            this.codes = {};
            this.loading = true;
            this.totalCount = Math.min(this.arUrls.length, 500);
            this.doneCount = 0;

            this.arUrls.forEach((element, i) => {
                if (i >= 500) return;
                setTimeout(() => {
                    this.HttpRequest(element, i);
                }, i * this.time);
            });
        },

        clearResults() {
            this.items = [];
            this.codes = {};
            this.loading = false;
            this.totalCount = 0;
            this.doneCount = 0;
            if (this.table && this.table.destroy) {
                try {
                    this.table.destroy();
                } catch (e) {
                    /* ignore */
                }
            }
            this.table = {};
        },

        detailHref(url) {
            return `?url=${encodeURIComponent(url)}#response-code`;
        },

        Sorting() {
            this.order = this.order === "desc" ? "asc" : "desc";
            this.items = _.orderBy(this.items, "code", this.order);
        },

        HttpRequest(url, i) {
            axios
                .get("/http-headers", {
                    params: { url: url, http: 1 },
                })
                .then((response) => {
                    const code = response.data;
                    const status = response.data === 200;

                    if (this.codes[code] === undefined) this.codes[code] = [];
                    this.codes[code].push(code);

                    this.items.push({ id: i, url: url, code: code, status: status });
                })
                .catch(() => {
                    const code = "—";
                    if (this.codes[code] === undefined) this.codes[code] = [];
                    this.codes[code].push(code);
                    this.items.push({ id: i, url: url, code: code, status: false });
                })
                .finally(() => {
                    this.doneCount += 1;
                    if (this.doneCount >= this.totalCount) {
                        this.loading = false;
                    }
                });
        },

        StringToArray() {
            if (this.urls.length) {
                this.arUrls = _.compact(this.urls.split(/[\r\n]+/));
            } else {
                this.arUrls = [];
            }
        },
    },
    updated() {
        this.$nextTick(function () {
            const table = $(this.$el).find(".cabinet-hh-results-table");

            if (
                table.length > 0 &&
                !this.loading &&
                this.arUrls.length > 0 &&
                this.arUrls.length === this.items.length
            ) {
                if ($.fn.DataTable.isDataTable(table)) {
                    table.DataTable().destroy();
                }

                this.table = table.DataTable({
                    destroy: true,
                    dom: "BtB",
                    ordering: false,
                    searching: false,
                    paging: false,
                    buttons: [
                        { extend: "csv", className: "btn btn-outline-secondary btn-sm" },
                        { extend: "excel", className: "btn btn-outline-secondary btn-sm" },
                        { extend: "pdf", className: "btn btn-outline-secondary btn-sm" },
                        { extend: "copy", className: "btn btn-outline-secondary btn-sm" },
                        { extend: "print", className: "btn btn-outline-secondary btn-sm" },
                    ],
                });

                this.table.buttons().container().addClass("dt-buttons px-3");
            }
        });
    },
};
</script>
