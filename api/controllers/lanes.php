<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../models/Lane.php';
require_once __DIR__ . '/../models/Task.php';

/**
 * Lanes Controller - handles swimlanes and columns REST endpoints
 */
class LanesController {
    private LaneModel $laneModel;
    private TaskModel $taskModel;
    
    public function __construct(string $dataDir) {
        $this->laneModel = new LaneModel($dataDir);
        $this->taskModel = new TaskModel($dataDir);
    }
    
    public function handle(string $method, array $pathParts): void {
        $action = $pathParts[0] ?? '';
        
        switch ($method) {
            case 'GET':
                $this->handleGet($action, $pathParts);
                break;
            case 'POST':
                $this->handlePost($action);
                break;
            case 'PUT':
                $this->handlePut($action);
                break;
            case 'DELETE':
                $this->handleDelete($action);
                break;
            default:
                json_error('Method not allowed', 405);
        }
    }
    
    private function handleGet(string $action, array $pathParts): void {
        switch ($action) {
            case '':
            case 'structured':
                json_response([
                    'swimlanes' => $this->laneModel->getStructured(),
                    'lastModified' => $this->laneModel->getLastModified()
                ]);
                break;
            case 'raw':
                json_response(['lanes' => $this->laneModel->getAll()]);
                break;
            default:
                json_error('Invalid action', 400);
        }
    }
    
    private function handlePost(string $action): void {
        $data = get_json_input();
        
        switch ($action) {
            case 'swimlane':
                $this->addSwimlane($data);
                break;
            case 'column':
                $this->addColumn($data);
                break;
            case 'meta':
                $this->updateMeta($data);
                break;
            default:
                json_error('Invalid action', 400);
        }
    }
    
    private function handlePut(string $action): void {
        $data = get_json_input();
        
        switch ($action) {
            case 'swimlane':
                $this->renameSwimlane($data);
                break;
            case 'column':
                $this->renameColumn($data);
                break;
            default:
                json_error('Invalid action', 400);
        }
    }
    
    private function handleDelete(string $action): void {
        $data = get_json_input();
        
        switch ($action) {
            case 'swimlane':
                $this->deleteSwimlane($data);
                break;
            case 'column':
                $this->deleteColumn($data);
                break;
            default:
                json_error('Invalid action', 400);
        }
    }
    
    private function addSwimlane(array $data): void {
        $swimlane = trim($data['swimlane'] ?? '');
        $firstColumn = trim($data['first_column'] ?? '');
        
        if ($swimlane === '' || $firstColumn === '') {
            json_error('Swimlane and first column are required');
        }
        
        if ($this->laneModel->validSwimlane($swimlane)) {
            json_error('Swimlane already exists');
        }
        
        if ($this->laneModel->addSwimlane($swimlane, $firstColumn)) {
            json_response(['message' => 'Swimlane added', 'swimlane' => $swimlane], 201);
        } else {
            json_error('Failed to add swimlane', 500);
        }
    }
    
    private function renameSwimlane(array $data): void {
        $oldName = trim($data['old_swimlane'] ?? '');
        $newName = trim($data['new_swimlane'] ?? '');
        
        if ($newName === '' || $oldName === '') {
            json_error('Old and new swimlane names are required');
        }
        
        if (!$this->laneModel->validSwimlane($oldName)) {
            json_error('Swimlane not found', 404);
        }
        
        if ($this->laneModel->validSwimlane($newName)) {
            json_error('New swimlane name already exists');
        }
        
        $this->laneModel->renameSwimlane($oldName, $newName);
        $this->taskModel->renameSwimlane($oldName, $newName);
        
        json_response(['message' => 'Swimlane renamed']);
    }
    
    private function deleteSwimlane(array $data): void {
        $swimlane = trim($data['swimlane'] ?? '');
        $fallback = trim($data['fallback_swimlane'] ?? '');
        
        if (!$this->laneModel->validSwimlane($swimlane)) {
            json_error('Swimlane not found', 404);
        }
        
        if ($swimlane === $fallback) {
            json_error('Fallback cannot be the same as swimlane to delete');
        }
        
        if (!$this->laneModel->validSwimlane($fallback)) {
            json_error('Fallback swimlane not found', 404);
        }
        
        $fallbackColumn = $this->laneModel->firstColumn($fallback);
        $this->taskModel->moveTasksFromSwimlane($swimlane, $fallback, $fallbackColumn);
        $this->laneModel->deleteSwimlane($swimlane);
        
        json_response(['message' => 'Swimlane deleted']);
    }
    
    private function addColumn(array $data): void {
        $swimlane = trim($data['swimlane'] ?? '');
        $column = trim($data['column'] ?? '');
        
        if ($swimlane === '' || $column === '') {
            json_error('Swimlane and column are required');
        }
        
        if (!$this->laneModel->validSwimlane($swimlane)) {
            json_error('Swimlane not found', 404);
        }
        
        if ($this->laneModel->validColumn($swimlane, $column)) {
            json_error('Column already exists in this swimlane');
        }
        
        if ($this->laneModel->addColumn($swimlane, $column)) {
            json_response(['message' => 'Column added', 'column' => $column], 201);
        } else {
            json_error('Failed to add column', 500);
        }
    }
    
    private function renameColumn(array $data): void {
        $swimlane = trim($data['swimlane'] ?? '');
        $oldName = trim($data['old_column'] ?? '');
        $newName = trim($data['new_column'] ?? '');
        
        if ($swimlane === '' || $oldName === '' || $newName === '') {
            json_error('Swimlane, old column, and new column are required');
        }
        
        if (!$this->laneModel->validSwimlane($swimlane)) {
            json_error('Swimlane not found', 404);
        }
        
        if (!$this->laneModel->validColumn($swimlane, $oldName)) {
            json_error('Column not found', 404);
        }
        
        if ($this->laneModel->validColumn($swimlane, $newName)) {
            json_error('New column name already exists in this swimlane');
        }
        
        $this->laneModel->renameColumn($swimlane, $oldName, $newName);
        $this->taskModel->renameColumn($swimlane, $oldName, $newName);
        
        json_response(['message' => 'Column renamed']);
    }
    
    private function deleteColumn(array $data): void {
        $swimlane = trim($data['swimlane'] ?? '');
        $column = trim($data['column'] ?? '');
        $fallback = trim($data['fallback_column'] ?? '');
        
        if (!$this->laneModel->validColumn($swimlane, $column)) {
            json_error('Column not found', 404);
        }
        
        if ($column === $fallback) {
            json_error('Fallback cannot be the same as column to delete');
        }
        
        if (!$this->laneModel->validColumn($swimlane, $fallback)) {
            json_error('Fallback column not found', 404);
        }
        
        $this->taskModel->moveTasksFromColumn($swimlane, $column, $fallback);
        $this->laneModel->deleteColumn($swimlane, $column);
        
        json_response(['message' => 'Column deleted']);
    }
    
    private function updateMeta(array $data): void {
        if (empty($data['swimlanes'])) {
            json_error('Invalid meta data');
        }
        
        if ($this->laneModel->updateMeta($data)) {
            json_response(['message' => 'Settings saved']);
        } else {
            json_error('Failed to save settings', 500);
        }
    }
}
