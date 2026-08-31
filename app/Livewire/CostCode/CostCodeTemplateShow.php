<?php

namespace App\Livewire\CostCode;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Models\CostCode;
use App\Models\CostCodeTemplate;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithFileUploads;

class CostCodeTemplateShow extends Component
{
    use AuthorizesAbility;

    use WithFileUploads;

    public CostCodeTemplate $template;

    /** The add/edit cost code dialog. */
    public const FORM_MODAL = 'cost-code-form-modal';

    // Form state for adding/editing cost codes
    public $editingCostCodeId = null;
    public $parentId = null;

    // Form fields
    public $code = '';
    public $name = '';
    public $description = '';
    public $sort_order = 0;

    // Import state
    public $showImportModal = false;
    public $importFile = null;
    public $importMode = 'merge';
    public $importErrors = [];
    public $importPreview = [];

    protected function rules()
    {
        $uniqueRule = 'unique:cost_codes,code,';
        $uniqueRule .= $this->editingCostCodeId ? $this->editingCostCodeId : 'NULL';
        $uniqueRule .= ',id,template_id,' . $this->template->id;

        return [
            'code' => ['required', 'string', 'max:50', $uniqueRule],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'sort_order' => 'integer|min:0',
        ];
    }

    public function mount(CostCodeTemplate $template)
    {
        $this->authorizeAbility('cost-codes.view');

        $this->template = $template->load(['creator', 'parentCostCodes.children']);
    }

    public function openAddForm($parentId = null)
    {
        $this->authorizeAbility('cost-codes.create');

        $this->resetForm();
        $this->parentId = $parentId;
        $this->sort_order = $this->nextSortOrder($parentId);

        $this->dispatch('open-modal', self::FORM_MODAL);
    }

    /**
     * The next free position under a parent, so the user never has to key one in.
     */
    private function nextSortOrder($parentId): int
    {
        $query = CostCode::where('template_id', $this->template->id);

        if ($parentId) {
            $query->where('parent_id', $parentId);
        } else {
            $query->whereNull('parent_id');
        }

        return (int) $query->max('sort_order') + 1;
    }

    public function openEditForm($costCodeId)
    {
        $this->authorizeAbility('cost-codes.edit');

        $costCode = CostCode::findOrFail($costCodeId);

        $this->resetForm();
        $this->editingCostCodeId = $costCode->id;
        $this->parentId = $costCode->parent_id;
        $this->code = $costCode->code;
        $this->name = $costCode->name;
        $this->description = $costCode->description ?? '';
        $this->sort_order = $costCode->sort_order;

        $this->dispatch('open-modal', self::FORM_MODAL);
    }

    /**
     * @param  bool  $addAnother  Keep the dialog open, cleared and ready for the
     *                            next code under the same parent. Building a
     *                            template is done in runs, not one code at a time.
     */
    public function save($addAnother = false)
    {
        $this->authorizeAbility($this->editingCostCodeId ? 'cost-codes.edit' : 'cost-codes.create');

        $this->validate();

        $data = [
            'template_id' => $this->template->id,
            'parent_id' => $this->parentId,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description ?: null,
            'sort_order' => $this->sort_order,
        ];

        if ($this->editingCostCodeId) {
            $costCode = CostCode::findOrFail($this->editingCostCodeId);
            $costCode->update($data);
            session()->flash('message', __('Cost code updated successfully.'));
        } else {
            CostCode::create($data);
            session()->flash('message', __('Cost code added successfully.'));
        }

        $this->refreshTemplate();

        if ($addAnother && ! $this->editingCostCodeId) {
            $parentId = $this->parentId;
            $this->resetForm();
            $this->parentId = $parentId;
            $this->sort_order = $this->nextSortOrder($parentId);
            $this->dispatch('cost-code-saved');

            return;
        }

        $this->closeForm();
    }

    public function deleteCostCode($costCodeId)
    {
        $this->authorizeAbility('cost-codes.delete');

        $costCode = CostCode::findOrFail($costCodeId);

        // Check if it has children
        if ($costCode->children()->count() > 0) {
            session()->flash('error', __('Cannot delete a cost code that has child codes. Delete the children first.'));
            return;
        }

        $costCode->delete();
        session()->flash('message', __('Cost code deleted successfully.'));
        $this->refreshTemplate();
    }

    public function closeForm()
    {
        $this->resetForm();
        $this->dispatch('close-modal', self::FORM_MODAL);
    }

    private function resetForm()
    {
        $this->editingCostCodeId = null;
        $this->parentId = null;
        $this->code = '';
        $this->name = '';
        $this->description = '';
        $this->sort_order = 0;
        $this->resetValidation();
    }

    private function refreshTemplate()
    {
        $this->template = $this->template->fresh(['creator', 'parentCostCodes.children']);
    }

    // Import methods
    public function openImportModal()
    {
        $this->authorizeAbility('cost-codes.create');

        $this->reset(['importFile', 'importErrors', 'importPreview']);
        $this->importMode = 'merge';
        $this->showImportModal = true;
    }

    public function closeImportModal()
    {
        $this->showImportModal = false;
        $this->reset(['importFile', 'importErrors', 'importPreview']);
    }

    /**
     * Take the chosen CSV back off, and the preview read from it with it.
     *
     * Guarded like every other action on this screen: the preview it clears is
     * built from the file, and building it is `cost-codes.create`.
     */
    public function clearImportFile()
    {
        $this->authorizeAbility('cost-codes.create');

        $this->importFile?->delete();

        $this->reset(['importFile', 'importErrors', 'importPreview']);
    }

    public function updatedImportFile()
    {
        $this->authorizeAbility('cost-codes.create');

        $this->validate([
            'importFile' => 'required|file|mimes:csv,txt|max:1024',
        ]);

        $this->previewImport();
    }

    private function previewImport()
    {
        $this->importErrors = [];
        $this->importPreview = [];

        if (!$this->importFile) {
            return;
        }

        $path = $this->importFile->getRealPath();

        // Read the file and bring whatever the spreadsheet exported to UTF-8
        $content = $this->normalizeToUtf8(file_get_contents($path));

        // Drop control characters that would corrupt the values (keep tabs and line breaks)
        $content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $content) ?? $content;

        // Parse through a stream so quoted fields may contain commas and line breaks,
        // and so \r\n, \n and \r line endings all work
        $rows = $this->parseCsv($content, $this->detectDelimiter($content));

        if (count($rows) < 2) {
            $this->importErrors[] = 'CSV file is empty or has no data rows.';
            return;
        }

        $header = array_map('trim', array_map('strtolower', $rows[0]));
        array_shift($rows);

        // Validate required columns
        $requiredColumns = ['code', 'name'];
        foreach ($requiredColumns as $col) {
            if (!in_array($col, $header)) {
                $this->importErrors[] = "Missing required column: {$col}";
            }
        }

        if (!empty($this->importErrors)) {
            return;
        }

        // Map rows to data
        foreach ($rows as $index => $row) {
            $rowNum = $index + 2;

            // Skip empty rows
            if (empty(array_filter($row))) {
                continue;
            }

            if (count($row) !== count($header)) {
                $this->importErrors[] = "Row {$rowNum}: Column count mismatch";
                continue;
            }

            $data = array_combine($header, array_map('trim', $row));

            // Validate required fields
            if (empty($data['code'])) {
                $this->importErrors[] = "Row {$rowNum}: Code is required";
                continue;
            }

            if (empty($data['name'])) {
                $this->importErrors[] = "Row {$rowNum}: Name is required";
                continue;
            }

            // Check for max 2 levels (if parent_code references another child)
            $parentCode = $data['parent_code'] ?? '';
            if (!empty($parentCode)) {
                $parentInPreview = collect($this->importPreview)->firstWhere('code', $parentCode);
                if ($parentInPreview && !empty($parentInPreview['parent_code'])) {
                    $this->importErrors[] = "Row {$rowNum}: Maximum 2 levels allowed. '{$data['code']}' cannot be a child of '{$parentCode}' (which is already a child).";
                    continue;
                }
            }

            $this->importPreview[] = [
                'code' => $data['code'],
                'name' => $data['name'],
                'description' => $data['description'] ?? '',
                'parent_code' => $parentCode,
            ];
        }

        // Check for duplicate codes in preview
        $codes = array_column($this->importPreview, 'code');
        $duplicates = array_diff_assoc($codes, array_unique($codes));
        foreach ($duplicates as $dup) {
            $this->importErrors[] = "Duplicate code found: {$dup}";
        }
    }

    /**
     * Convert raw CSV bytes to UTF-8, whatever the spreadsheet exported.
     *
     * Handles UTF-8 (with or without BOM), UTF-16 LE/BE ("Unicode text" from Excel)
     * and the single-byte legacy encodings: Mac Roman (Excel for Mac) and
     * Windows-1252 / ISO-8859-1 (Excel for Windows).
     */
    private function normalizeToUtf8(string $content): string
    {
        // UTF-16 exports always carry a BOM
        if (str_starts_with($content, "\xFF\xFE")) {
            return mb_convert_encoding(substr($content, 2), 'UTF-8', 'UTF-16LE');
        }

        if (str_starts_with($content, "\xFE\xFF")) {
            return mb_convert_encoding(substr($content, 2), 'UTF-8', 'UTF-16BE');
        }

        // UTF-8 BOM: strip it, the rest is already UTF-8
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            return substr($content, 3);
        }

        // Already valid UTF-8: leave it exactly as it is
        if (mb_check_encoding($content, 'UTF-8')) {
            return $content;
        }

        // Legacy single byte file. Every byte is valid in both Mac Roman and
        // Windows-1252, so decide by where the high bytes sit: in Mac Roman the
        // accented letters live in 0x80-0x9F, in Windows-1252 they live in 0xC0-0xFF.
        $macRange = preg_match_all('/[\x80-\x9F]/', $content);
        $winRange = preg_match_all('/[\xC0-\xFF]/', $content);

        if ($macRange > $winRange && function_exists('iconv')) {
            $converted = @iconv('MACINTOSH', 'UTF-8//TRANSLIT', $content);

            if ($converted !== false) {
                return $converted;
            }
        }

        return mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
    }

    /**
     * Pick the column separator: Excel in a pt_BR locale writes semicolons.
     */
    private function detectDelimiter(string $content): string
    {
        $firstLine = strtok($content, "\r\n") ?: '';

        $counts = [
            ',' => substr_count($firstLine, ','),
            ';' => substr_count($firstLine, ';'),
            "\t" => substr_count($firstLine, "\t"),
        ];

        arsort($counts);
        $delimiter = array_key_first($counts);

        return $counts[$delimiter] > 0 ? $delimiter : ',';
    }

    /**
     * Parse CSV content into rows, skipping blank lines.
     */
    private function parseCsv(string $content, string $delimiter): array
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);

        $rows = [];

        while (($row = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            // fgetcsv returns [null] for a blank line
            if ($row === null || count(array_filter($row, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }

            $rows[] = array_map(fn ($value) => (string) $value, $row);
        }

        fclose($handle);

        return $rows;
    }

    public function executeImport()
    {
        $this->authorizeAbility('cost-codes.create');

        if (!empty($this->importErrors) || empty($this->importPreview)) {
            return;
        }

        DB::transaction(function () {
            // If replace mode, delete existing items
            if ($this->importMode === 'replace') {
                $this->template->costCodes()->delete();
            }

            $codeToId = [];

            // First pass: create/update all parent items (no parent_code)
            $order = 0;
            foreach ($this->importPreview as $data) {
                if (empty($data['parent_code'])) {
                    $item = $this->template->costCodes()->updateOrCreate(
                        ['code' => $data['code']],
                        [
                            'name' => $data['name'],
                            'description' => $data['description'] ?: null,
                            'parent_id' => null,
                            'sort_order' => $order++,
                        ]
                    );
                    $codeToId[$data['code']] = $item->id;
                }
            }

            // Second pass: create/update child items
            $order = 0;
            foreach ($this->importPreview as $data) {
                if (!empty($data['parent_code'])) {
                    // Find parent ID
                    $parentId = $codeToId[$data['parent_code']]
                        ?? $this->template->costCodes()->where('code', $data['parent_code'])->value('id');

                    if (!$parentId) {
                        continue; // Skip if parent not found
                    }

                    $item = $this->template->costCodes()->updateOrCreate(
                        ['code' => $data['code']],
                        [
                            'name' => $data['name'],
                            'description' => $data['description'] ?: null,
                            'parent_id' => $parentId,
                            'sort_order' => $order++,
                        ]
                    );
                    $codeToId[$data['code']] = $item->id;
                }
            }
        });

        $count = count($this->importPreview);
        $this->closeImportModal();
        $this->refreshTemplate();
        session()->flash('message', __(':count cost codes imported successfully.', ['count' => $count]));
    }

    public function downloadSampleCsv()
    {
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="cost-codes-sample.csv"',
        ];

        $callback = function () {
            $file = fopen('php://output', 'w');
            // UTF-8 BOM so Excel opens (and saves) the file as UTF-8
            fwrite($file, "\xEF\xBB\xBF");
            fputcsv($file, ['code', 'name', 'description', 'parent_code']);
            fputcsv($file, ['01', 'General Requirements', 'General project requirements', '']);
            fputcsv($file, ['01.1', 'Summary of Work', 'Project scope summary', '01']);
            fputcsv($file, ['01.2', 'Price and Payment', 'Payment procedures', '01']);
            fputcsv($file, ['02', 'Site Work', 'Site related work', '']);
            fputcsv($file, ['02.1', 'Site Preparation', 'Prepare the site', '02']);
            fputcsv($file, ['02.2', 'Demolition', 'Demolition work', '02']);
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function render()
    {
        return view('livewire.cost-code.cost-code-template-show')
            ->layout('components.layouts.app');
    }
}
