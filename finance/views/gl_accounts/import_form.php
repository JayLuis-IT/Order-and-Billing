<div class="d-flex justify-content-between mb-4">
    <div class="d-flex gap-2">
        <a href="?controller=finance&action=glAccounts" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to GL Accounts</a>
        <a href="?controller=finance&action=glAccountImportTemplate" class="btn btn-success"><i class="bi bi-download me-1"></i>Download Template</a>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card data-card">
            <div class="card-header"><i class="bi bi-upload me-2"></i>Import GL Accounts (CSV / XLSX)</div>
            <div class="card-body">
                <form method="POST" action="?controller=finance&action=glAccountImportPreview" enctype="multipart/form-data">
                    <div class="mb-4">
                        <label class="form-label">Select File (.csv or .xlsx)</label>
                        <input type="file" name="import_file" class="form-control" accept=".csv,.xlsx" required>
                        <div class="form-text">Row 1 must contain the column headers. Max 10MB.</div>
                    </div>

                    <div class="alert alert-info py-3 mb-4">
                        <h6 class="alert-heading mb-2"><i class="bi bi-info-circle me-1"></i>Expected Columns</h6>
                        <code>GL Account Code, GL Account Description, Report Included, Grouping in Financial Report</code>
                        <hr class="my-2">
                        <small>Header matching ignores case, spaces and underscores — <code>GL Account Code</code>, <code>gl_account_code</code> and <code>glaccountcode</code> are all accepted. Optional aliases: <code>code</code>, <code>description</code>, <code>report</code>, <code>grouping</code>.</small>
                    </div>

                    <div class="alert alert-warning py-2 mb-4">
                        <small><i class="bi bi-exclamation-triangle me-1"></i>Rows whose <strong>GL Account Code already exists</strong> will be <strong>skipped</strong> — only new codes are created. Duplicate codes inside the file are flagged as errors.</small>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="?controller=finance&action=glAccounts" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-success"><i class="bi bi-upload me-1"></i>Upload &amp; Preview</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
