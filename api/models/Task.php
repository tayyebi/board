<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/csv.php';
require_once __DIR__ . '/../lib/helpers.php';

/**
 * Task Model - handles tasks data
 */
class TaskModel {
    private string $path;
    private array $headers = ['id', 'title_b64', 'notes_b64', 'swimlane_b64', 'column_b64', 'due'];
    
    public function __construct(string $dataDir) {
        $this->path = $dataDir . '/tasks.csv';
        ensure_csv($this->path, $this->headers);
    }
    
    public function getAll(): array {
        return load_rows($this->path);
    }
    
    public function getDecoded(): array {
        $rows = $this->getAll();
        $tasks = [];
        
        foreach ($rows as $row) {
            $tasks[] = [
                'id' => $row['id'] ?? '',
                'title' => b64d($row['title_b64'] ?? ''),
                'notes' => b64d($row['notes_b64'] ?? ''),
                'swimlane' => b64d($row['swimlane_b64'] ?? ''),
                'column' => b64d($row['column_b64'] ?? ''),
                'due' => $row['due'] ?? ''
            ];
        }
        
        return $tasks;
    }
    
    public function getById(string $id): ?array {
        $rows = $this->getAll();
        foreach ($rows as $row) {
            if (($row['id'] ?? '') === $id) {
                return [
                    'id' => $row['id'],
                    'title' => b64d($row['title_b64'] ?? ''),
                    'notes' => b64d($row['notes_b64'] ?? ''),
                    'swimlane' => b64d($row['swimlane_b64'] ?? ''),
                    'column' => b64d($row['column_b64'] ?? ''),
                    'due' => $row['due'] ?? ''
                ];
            }
        }
        return null;
    }
    
    public function save(array $rows): bool {
        return save_rows($this->path, $this->headers, $rows);
    }
    
    private function nextId(): string {
        $rows = $this->getAll();
        $max = 0;
        foreach ($rows as $t) {
            $max = max($max, (int)($t['id'] ?? 0));
        }
        return (string)($max + 1);
    }
    
    public function create(string $title, string $notes, string $swimlane, string $column, string $due): string {
        $rows = $this->getAll();
        $id = $this->nextId();
        
        $rows[] = [
            'id' => $id,
            'title_b64' => b64e($title),
            'notes_b64' => b64e($notes),
            'swimlane_b64' => b64e($swimlane),
            'column_b64' => b64e($column),
            'due' => $due
        ];
        
        $this->save($rows);
        return $id;
    }
    
    public function update(string $id, string $title, string $notes, string $swimlane, string $column, string $due): bool {
        $rows = $this->getAll();
        $found = false;
        
        foreach ($rows as &$t) {
            if ($t['id'] === $id) {
                if ($title !== '') $t['title_b64'] = b64e($title);
                $t['notes_b64'] = b64e($notes);
                $t['swimlane_b64'] = b64e($swimlane);
                $t['column_b64'] = b64e($column);
                $t['due'] = $due;
                $found = true;
                break;
            }
        }
        
        if ($found) {
            return $this->save($rows);
        }
        return false;
    }
    
    public function move(string $id, string $swimlane, string $column): bool {
        $rows = $this->getAll();
        $found = false;
        
        foreach ($rows as &$t) {
            if (($t['id'] ?? '') === $id) {
                $t['swimlane_b64'] = b64e($swimlane);
                $t['column_b64'] = b64e($column);
                $found = true;
                break;
            }
        }
        
        if ($found) {
            return $this->save($rows);
        }
        return false;
    }
    
    public function delete(string $id): bool {
        $rows = $this->getAll();
        $rows = array_values(array_filter($rows, fn($t) => ($t['id'] ?? '') !== $id));
        return $this->save($rows);
    }
    
    public function renameSwimlane(string $oldName, string $newName): bool {
        $rows = $this->getAll();
        foreach ($rows as &$t) {
            if (b64d($t['swimlane_b64'] ?? '') === $oldName) {
                $t['swimlane_b64'] = b64e($newName);
            }
        }
        return $this->save($rows);
    }
    
    public function moveTasksFromSwimlane(string $swimlane, string $targetSwimlane, string $targetColumn): bool {
        $rows = $this->getAll();
        foreach ($rows as &$t) {
            if (b64d($t['swimlane_b64'] ?? '') === $swimlane) {
                $t['swimlane_b64'] = b64e($targetSwimlane);
                $t['column_b64'] = b64e($targetColumn);
            }
        }
        return $this->save($rows);
    }
    
    public function renameColumn(string $swimlane, string $oldName, string $newName): bool {
        $rows = $this->getAll();
        foreach ($rows as &$t) {
            if (b64d($t['swimlane_b64'] ?? '') === $swimlane && b64d($t['column_b64'] ?? '') === $oldName) {
                $t['column_b64'] = b64e($newName);
            }
        }
        return $this->save($rows);
    }
    
    public function moveTasksFromColumn(string $swimlane, string $column, string $targetColumn): bool {
        $rows = $this->getAll();
        foreach ($rows as &$t) {
            if (b64d($t['swimlane_b64'] ?? '') === $swimlane && b64d($t['column_b64'] ?? '') === $column) {
                $t['column_b64'] = b64e($targetColumn);
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
    
    public function getCount(): int {
        return count($this->getAll());
    }
}
