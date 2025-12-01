<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/csv.php';
require_once __DIR__ . '/../lib/helpers.php';

/**
 * Lane Model - handles swimlanes and columns data
 */
class LaneModel {
    private string $path;
    private array $headers = ['swimlane_b64', 'column_b64', 'swimlane_color', 'column_color', 'swimlane_order'];
    
    public function __construct(string $dataDir) {
        $this->path = $dataDir . '/lanes.csv';
        ensure_csv($this->path, $this->headers);
    }
    
    public function getAll(): array {
        return load_rows($this->path);
    }
    
    public function getStructured(): array {
        $rows = $this->getAll();
        $swimlanes = [];
        
        if (empty($rows)) {
            $rows = $this->seedDefault();
        }
        
        foreach ($rows as $lr) {
            $swl = b64d($lr['swimlane_b64'] ?? '');
            $col = b64d($lr['column_b64'] ?? '');
            $scolor = $lr['swimlane_color'] ?? '';
            $ccolor = $lr['column_color'] ?? '';
            $order = is_numeric($lr['swimlane_order'] ?? '') ? (int)$lr['swimlane_order'] : 0;
            
            if ($swl === '' || $col === '') continue;
            
            if (!isset($swimlanes[$swl])) {
                $swimlanes[$swl] = ['cols' => [], 'color' => $scolor, 'order' => $order];
            }
            if (!in_array($col, $swimlanes[$swl]['cols'], true)) {
                $swimlanes[$swl]['cols'][] = $col;
            }
            if ($swimlanes[$swl]['color'] === '') {
                $swimlanes[$swl]['color'] = $scolor;
            }
        }
        
        uasort($swimlanes, fn($a, $b) => ($a['order'] <=> $b['order']));
        return $swimlanes;
    }
    
    private function seedDefault(): array {
        $seed = [];
        $defaults = [
            ['Incidents', ['Detected', 'Investigating', 'Fixing', 'Verifying', 'Postmortem']],
            ['Ops backlog', ['Triaged', 'Planned', 'In progress', 'Review', 'Done']]
        ];
        
        foreach ($defaults as [$swl, $cols]) {
            foreach ($cols as $c) {
                $seed[] = [
                    'swimlane_b64' => b64e($swl),
                    'column_b64' => b64e($c),
                    'swimlane_color' => '',
                    'column_color' => '',
                    'swimlane_order' => '0'
                ];
            }
        }
        
        save_rows($this->path, $this->headers, $seed);
        return $seed;
    }
    
    public function save(array $rows): bool {
        return save_rows($this->path, $this->headers, $rows);
    }
    
    public function addSwimlane(string $swimlane, string $firstColumn): bool {
        $structured = $this->getStructured();
        if (isset($structured[$swimlane])) return false;
        
        $maxOrder = 0;
        foreach ($structured as $meta) {
            $maxOrder = max($maxOrder, $meta['order'] ?? 0);
        }
        
        $rows = $this->getAll();
        $rows[] = [
            'swimlane_b64' => b64e($swimlane),
            'column_b64' => b64e($firstColumn),
            'swimlane_color' => '',
            'column_color' => '',
            'swimlane_order' => (string)($maxOrder + 1)
        ];
        
        return $this->save($rows);
    }
    
    public function renameSwimlane(string $oldName, string $newName): bool {
        $rows = $this->getAll();
        foreach ($rows as &$r) {
            if (b64d($r['swimlane_b64'] ?? '') === $oldName) {
                $r['swimlane_b64'] = b64e($newName);
            }
        }
        return $this->save($rows);
    }
    
    public function deleteSwimlane(string $swimlane): bool {
        $rows = $this->getAll();
        $rows = array_values(array_filter($rows, fn($r) => b64d($r['swimlane_b64'] ?? '') !== $swimlane));
        return $this->save($rows);
    }
    
    public function addColumn(string $swimlane, string $column): bool {
        $structured = $this->getStructured();
        if (!isset($structured[$swimlane])) return false;
        if (in_array($column, $structured[$swimlane]['cols'], true)) return false;
        
        $rows = $this->getAll();
        $rows[] = [
            'swimlane_b64' => b64e($swimlane),
            'column_b64' => b64e($column),
            'swimlane_color' => '',
            'column_color' => '',
            'swimlane_order' => (string)($structured[$swimlane]['order'] ?? 0)
        ];
        
        return $this->save($rows);
    }
    
    public function renameColumn(string $swimlane, string $oldName, string $newName): bool {
        $rows = $this->getAll();
        foreach ($rows as &$r) {
            if (b64d($r['swimlane_b64'] ?? '') === $swimlane && b64d($r['column_b64'] ?? '') === $oldName) {
                $r['column_b64'] = b64e($newName);
            }
        }
        return $this->save($rows);
    }
    
    public function deleteColumn(string $swimlane, string $column): bool {
        $rows = $this->getAll();
        $rows = array_values(array_filter($rows, fn($r) => 
            !(b64d($r['swimlane_b64'] ?? '') === $swimlane && b64d($r['column_b64'] ?? '') === $column)
        ));
        return $this->save($rows);
    }
    
    public function updateMeta(array $meta): bool {
        $rows = $this->getAll();
        $map = $meta['swimlanes'] ?? [];
        
        foreach ($rows as &$lr) {
            $swl = b64d($lr['swimlane_b64'] ?? '');
            $col = b64d($lr['column_b64'] ?? '');
            
            if (isset($map[$swl])) {
                $lr['swimlane_color'] = $map[$swl]['color'] ?? ($lr['swimlane_color'] ?? '');
                $lr['swimlane_order'] = (string)($map[$swl]['order'] ?? ($lr['swimlane_order'] ?? '0'));
                if (isset($map[$swl]['columns'][$col])) {
                    $lr['column_color'] = $map[$swl]['columns'][$col];
                }
            }
        }
        
        return $this->save($rows);
    }
    
    public function getLastModified(): ?string {
        if (file_exists($this->path)) {
            return date('Y-m-d H:i', filemtime($this->path));
        }
        return null;
    }
    
    public function validSwimlane(string $swimlane): bool {
        return array_key_exists($swimlane, $this->getStructured());
    }
    
    public function validColumn(string $swimlane, string $column): bool {
        $structured = $this->getStructured();
        return isset($structured[$swimlane]) && in_array($column, $structured[$swimlane]['cols'], true);
    }
    
    public function firstSwimlane(): string {
        $structured = $this->getStructured();
        return array_key_first($structured) ?? '';
    }
    
    public function firstColumn(string $swimlane): string {
        $structured = $this->getStructured();
        return $structured[$swimlane]['cols'][0] ?? '';
    }
}
