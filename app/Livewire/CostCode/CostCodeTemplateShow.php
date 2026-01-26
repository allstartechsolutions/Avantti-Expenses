<?php

namespace App\Livewire\CostCode;

use App\Models\CostCode;
use App\Models\CostCodeTemplate;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithFileUploads;

class CostCodeTemplateShow extends Component
{
    use WithFileUploads;

    public CostCodeTemplate $template;

    // Form state for adding/editing cost codes
    public $showForm = false;
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
        $this->template = $template->load(['creator', 'parentCostCodes.children']);
    }

    public function openAddForm($parentId = null)
    {
        $this->resetForm();
        $this->parentId = $parentId;
        $this->showForm = true;

        // Set default sort order
        $query = CostCode::where('template_id', $this->template->id);
        if ($parentId) {
            $query->where('parent_id', $parentId);
        } else {
            $query->whereNull('parent_id');
        }
        $this->sort_order = $query->max('sort_order') + 1;
    }

    public function openEditForm($costCodeId)
    {
        $costCode = CostCode::findOrFail($costCodeId);

        $this->resetForm();
        $this->editingCostCodeId = $costCode->id;
        $this->parentId = $costCode->parent_id;
        $this->code = $costCode->code;
        $this->name = $costCode->name;
        $this->description = $costCode->description ?? '';
        $this->sort_order = $costCode->sort_order;
        $this->showForm = true;
    }

    public function save()
    {
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
            session()->flash('message', 'Cost code updated successfully.');
        } else {
            CostCode::create($data);
            session()->flash('message', 'Cost code added successfully.');
        }

        $this->closeForm();
        $this->refreshTemplate();
    }

    public function deleteCostCode($costCodeId)
    {
        $costCode = CostCode::findOrFail($costCodeId);

        // Check if it has children
        if ($costCode->children()->count() > 0) {
            session()->flash('error', 'Cannot delete a cost code that has child codes. Delete the children first.');
            return;
        }

        $costCode->delete();
        session()->flash('message', 'Cost code deleted successfully.');
        $this->refreshTemplate();
    }

    public function closeForm()
    {
        $this->showForm = false;
        $this->resetForm();
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
        $this->reset(['importFile', 'importErrors', 'importPreview']);
        $this->importMode = 'merge';
        $this->showImportModal = true;
    }

    public function closeImportModal()
    {
        $this->showImportModal = false;
        $this->reset(['importFile', 'importErrors', 'importPreview']);
    }

    public function updatedImportFile()
    {
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

        // Read file content and handle encoding
        $content = file_get_contents($path);

        // Remove BOM if present (UTF-8 BOM)
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        // Fix common corrupted Portuguese character sequences
        // These occur when files are saved with wrong encoding
        $corruptedReplacements = [
            "\x8d\x8b" => 'çã',  // ção
            "\x8d" => 'ç',       // ç alone
            "\x8b" => 'ã',       // ã alone
            "\x87" => 'á',       // á
            "\x88" => 'é',       // é
            "\x8c" => 'í',       // í
            "\x8e" => 'ó',       // ó
            "\x8f" => 'ú',       // ú
            "\x8a" => 'ê',       // ê
            "\x89" => 'ô',       // ô
        ];
        $content = str_replace(array_keys($corruptedReplacements), array_values($corruptedReplacements), $content);

        // Check if content is valid UTF-8, if not convert from Windows-1252
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        // Remove any remaining invalid UTF-8 sequences
        $content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $content);

        // Parse CSV from string - handle both \r\n and \n line endings
        $content = str_replace("\r\n", "\n", $content);
        $content = str_replace("\r", "\n", $content);
        $lines = explode("\n", $content);
        $rows = array_map(function($line) {
            return str_getcsv(trim($line));
        }, array_filter($lines, fn($line) => trim($line) !== ''));

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

    public function executeImport()
    {
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
        session()->flash('message', "{$count} cost codes imported successfully.");
    }

    public function downloadSampleCsv()
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="cost-codes-sample.csv"',
        ];

        $callback = function () {
            $file = fopen('php://output', 'w');
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
